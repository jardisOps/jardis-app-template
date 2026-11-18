---
name: support-acl
description: Per-record access entries (information kind, record, group, level) on a SQL table — grant, revoke, level lookup, three-valued access check, group reassignment, dbquery filters that restrict a record query to visible records, backfill of existing records.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns, support-dbquery, support-repository]
next: []
---

# ACL_COMPONENT_SKILL
> `jardissupport/acl` | NS: `JardisSupport\Acl` | No HTTP, no session, no group or user model, no transaction of its own | PHP 8.3+

## ARCHITECTURE
```
AclStore      (DbConnectionInterface, table)  grant / revoke / lookup / check / reassign on the entry table
AclFilter     (table; no connection)          builds dbquery objects that restrict a record query to visible records; runs nothing
AclBackfill   (DbConnectionInterface, table)  gives records without an entry one entry "all" (creator group, else fallback group)
```
Three Orchestrators in `src/` root; each method delegates to one Closure (invokable handler under `src/Handler/{Entry,Filter,Backfill,Table}/`) bound in the constructor via `->__invoke(...)`. No business logic in the Orchestrator. Execution goes through `jardissupport/repository` `QueryExecutor`, SQL is built with `jardissupport/dbquery` (prepared mode, upsert of `DbInsert`).

## TABLE (`resources/sql/{mysql,pgsql,sqlite}.sql`)
Three dialect files, all `CREATE ... IF NOT EXISTS`; default table name `acl_entries` (constructor argument `$table`, plain identifier, optionally `schema.table`; anything else → `InvalidArgumentException`). One row = one group reaches one record of one information kind up to a level.

| Column | MySQL / MariaDB, PostgreSQL | SQLite | Notes |
|---|---|---|---|
| `kind` | `INT NOT NULL` | `INTEGER NOT NULL` | information kind, first in every key |
| `record_id` | `BIGINT NOT NULL` | `INTEGER NOT NULL` | |
| `group_id` | `BIGINT NOT NULL` | `INTEGER NOT NULL` | |
| `level` | `SMALLINT NOT NULL` | `INTEGER NOT NULL` | 1 = view, 2 = edit, 3 = all |

- **Primary key:** `(kind, record_id, group_id)` — one entry per group and record.
- **Index `idx_acl_entries_group`:** `(kind, group_id, level, record_id)` — serves `AclFilter` and `levelOf`/`check`.
- **Index `idx_acl_entries_group_id`:** `(group_id)` — serves `hasEntriesOfGroup` and `reassignGroup` (search by group alone).
- MySQL: `ENGINE=InnoDB DEFAULT CHARSET=utf8mb4`, indexes inline as `KEY`; PostgreSQL and SQLite: `CREATE INDEX IF NOT EXISTS` after the table.
- The package does not run the DDL. An existing table gets a new index only when the application adds it (`CREATE TABLE IF NOT EXISTS` leaves an existing table unchanged).

## API / SIGNATURES

### AclStore
`final class AclStore` — `$kind`, `$recordId`, `$group`, `$from`, `$to` are `int`; `$groups` is `list<int>`.
```php
new AclStore(DbConnectionInterface $connection, string $table = 'acl_entries')

public function levelOf(int $kind, int $recordId, array $groups): ?AccessLevel
public function check(int $kind, int $recordId, array $groups, AccessLevel $required): AccessDecision
public function grant(int $kind, int $recordId, int $group, AccessLevel $level): void
public function grantAll(int $kind, int $recordId, array $entries): void          // @param list<Entry>
public function revoke(int $kind, int $recordId, int $group): void
public function isLastFullEntry(int $kind, int $recordId, int $group): bool
public function removeRecord(int $kind, int $recordId): int
public function reassignGroup(int $from, int $to): int
public function hasEntriesOfGroup(int $group): bool
public function entriesOf(int $kind, int $recordId): array                        // @return list<Entry>, ordered by group
```
- `levelOf`: highest level any of the groups holds; `null` = no entry (also empty group list, unknown kind, unknown record).
- `check`: from the same view as `levelOf` — `Granted` when `levelOf` ≥ required, `NotVisible` when `levelOf` is `null`, else `TooLow`. The caller branches on the result and never compares levels.
- `grant`: sets or overwrites the entry of the group.
- `grantAll`: empty list writes nothing; a group named twice keeps the last level; an existing entry is overwritten; one statement per distinct group, no transaction.
- `revoke`: removes the entry; a group without an entry is a no-op.
- `isLastFullEntry`: true when the group holds level `all` on the record and no other group does.
- `removeRecord`: removes every entry of the record, returns how many.
- `reassignGroup`: moves every entry of `$from` to `$to`, returns how many left `$from` (`$from === $to` → `0`); where `$to` already holds an entry on the record the higher level stays; batches of 500; several statements, no transaction.
- `hasEntriesOfGroup`: true when at least one entry of any kind carries the group.

### AclFilter
`final class AclFilter` — needs no connection, runs nothing, returns `DbQueryBuilderInterface` (`jardissupport/contracts`).
```php
public const int COUNT_CAP = 10000;

new AclFilter(string $table = 'acl_entries')

public function rowCondition(int $kind, array $groups, AccessLevel $min, string $keyColumn): DbQueryBuilderInterface
public function recordIds(int $kind, array $groups, AccessLevel $min): DbQueryBuilderInterface
```
- `rowCondition`: single-value subquery (`SELECT 1 ... LIMIT 1`) bound to the key column of the outer row; use as `->where(Expression::integer(1))->equals($filter->rowCondition(...))`. Name the key column with the outer alias (`d.id`) when its bare name also exists in the entry table (`kind`, `record_id`, `group_id`, `level`).
- `recordIds`: key set (`SELECT DISTINCT record_id`) for `->where('d.id')->in($filter->recordIds(...))`; an empty group list yields `1=0` (sees nothing).
- `COUNT_CAP` (since 0.2.0): fixed limit of the capped total of a filtered list — count as `where(key)->in(recordIds(...))`, sorted like the page, `limit(AclFilter::COUNT_CAP + 1)`, `COUNT(*)` from outside; above the cap the answer reports `COUNT_CAP` with a capped marker (`jardissupport/response` `ReadResult::ofCappedList`). Never hard-code the number.
- Both give the same set as `AclStore::levelOf`. Needs `jardissupport/dbquery` ≥ 1.3.1 (a subquery value is inlined in every condition, so the filter may stand behind other conditions).

### AclBackfill
`final class AclBackfill`
```php
new AclBackfill(DbConnectionInterface $connection, string $table = 'acl_entries')

public function run(
    int $kind,
    string $sourceTable,
    string $keyColumn,
    ?string $creatorColumn,
    ?string $tenantColumn,
    Closure $groupOfCreator,      // @param Closure(?string, list<string>): array<string, int>
    ?int $fallbackGroup,
    int $batch = 1000,
): BackfillResult
```
- Writes one entry `all` for each record of `$sourceTable` that holds no entry of the kind; records with an entry are never touched, a second run writes nothing.
- Group of a record: `$groupOfCreator($tenant, $creators)` returns the group per creator (called once per tenant and batch; tenant from `$tenantColumn`, `null` without one); no creator or creator unknown → `$fallbackGroup`; without a fallback group the record is skipped and stays without an entry.
- Names (`$sourceTable`, `$keyColumn`, `$creatorColumn`, `$tenantColumn`) are validated as plain identifiers before anything runs; `$batch < 1` → `InvalidArgumentException`. Works in key order, batch by batch; no transaction.

## DATA CLASSES (`src/Data/`)
```php
enum AccessLevel: int { case View = 1; case Edit = 2; case All = 3; }
public function word(): string                          // 'view' | 'edit' | 'all'
public static function fromWord(string $word): ?self    // exactly view|edit|all; any other word (case-sensitive, no trimming) → null

enum AccessDecision { case Granted; case NotVisible; case TooLow; }   // no backing value

final readonly class Entry
public function __construct(public int $group, public AccessLevel $level)

final readonly class BackfillResult
public function __construct(private int $written, private int $toFallback, private int $skipped)
public function written(): int      // entries written in total
public function toFallback(): int   // of those, given to the fallback group
public function skipped(): int      // records left without an entry (no creator group, no fallback)

final class AclRule     // private constructor, constants only
const NAME = 'LastFullEntryStays';  const MESSAGE_KEY = 'acl.lastFullEntry';
```
`AclRule` names the rule "the last entry with level all stays" for the caller's 422; `acl` only answers the question (`isLastFullEntry`) and rejects nothing. The words `view`, `edit`, `all` are the one spelling for model, request body and OpenAPI.

## USAGE
```php
$store = new AclStore($connection);                    // $connection: DbConnectionInterface
$store->grantAll(7, 1001, [new Entry(5, AccessLevel::All), new Entry(9, AccessLevel::View)]);

match ($store->check(7, 1001, [9], AccessLevel::Edit)) {
    AccessDecision::Granted    => /* proceed */,
    AccessDecision::TooLow     => /* 403 */,
    AccessDecision::NotVisible => /* 404 */,
};

$filter = new AclFilter();
$rows = (new DbQuery())->select('d.*')->from('documents', 'd')
    ->where(Expression::integer(1))->equals($filter->rowCondition(7, [9], AccessLevel::View, 'd.id'));
```

## RULES
- **No transaction of its own.** `grantAll`, `reassignGroup` and `AclBackfill::run` run several statements; the caller brackets them (`jardissupport/repository` `RunInTransaction`) when they must succeed or fail as a whole.
- **Numbers only.** `acl` knows `int` kind, record, group and the three levels; who a group is, who holds which group and how a record is created is the caller's.
- **Information kind comes first** in every key and every condition; a kind never reaches another kind's entries.
- **Rejection is the caller's.** `AccessDecision` and `isLastFullEntry` answer; the status (403/404/422) is chosen by the caller (`AclRule` supplies name and message key).
- **Case-sensitive words.** `AccessLevel::fromWord('View')` is `null`.

## DOES NOT DO
- No transaction, no commit, no rollback — the caller brackets (see RULES).
- No group, user, tenant or person model; no resolution of a person to groups.
- No HTTP layer, no session, no framework coupling, no event dispatching (no event class in `src/`).
- No rejection and no status code: `check()` returns `AccessDecision`; the 422 for "last full entry" is built by the caller from `AclRule`.
- No table creation or migration: the DDL ships in `resources/sql/`, the application runs it; an existing table does not get a new index by itself.
- `AclFilter` runs no query (builds only); it has no connection.
- No level other than `view`, `edit`, `all` (1, 2, 3); no deny or negative entries.
- No locking: no row lock around the read in `isLastFullEntry` or `check`.
- No column or table names other than plain identifiers (table: optionally `schema.table`) for the entry table and for the backfill source.
