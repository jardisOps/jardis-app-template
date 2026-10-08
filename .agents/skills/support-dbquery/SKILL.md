---
name: support-dbquery
description: Fluent SQL builder — SELECT, INSERT, UPDATE, DELETE, CTEs, window functions. Use for DbQuery, DbInsert, DbUpdate, jardissupport/dbquery.
user-invocable: false
zone: post-active
persona: C
prerequisites: [foundation-architecture, foundation-patterns, adapter-dbconnection]
next: []
---

# DBQUERY_COMPONENT_SKILL
> jardissupport/dbquery | NS: `JardisSupport\DbQuery` | SQL query builder | PHP 8.2+

## CORE CLASSES
| Class | Purpose |
|-------|---------|
| `DbQuery` | SELECT + CTEs + window functions + subqueries |
| `DbInsert` | INSERT + conflict handling |
| `DbUpdate` | UPDATE + JOIN |
| `DbDelete` | DELETE + JOIN |
| `DbPreparedQuery` | Result: `sql()`, `bindings()`, `type()` |
| `Dialect` | Enum: `MySQL`, `MariaDB`, `PostgreSQL`, `SQLite` |
| `Expression` | Raw SQL (not escaped) — `Expression::raw(string)` |

## SQL GENERATION
```php
// All builders share the same signature:
$q->sql(string $dialect, bool $prepared = true, ?string $version = null): DbPreparedQuery|string

$prepared = $q->sql('mysql', prepared: true);
$prepared->sql();       // SQL with ? placeholders
$prepared->bindings();  // array of values
$prepared->type();      // 'mysql'|'mariadb'|'postgres'|'sqlite'
(string) $prepared;     // same as ->sql()
```

## SELECT
```php
use JardisSupport\DbQuery\{DbQuery, Data\Dialect};

(new DbQuery())
    ->select('id, name')->distinct(true)
    ->from('users')
    ->where('status')->equals('active')
    ->where('age')->greater(18)
    ->orderBy('name', 'ASC')->limit(10)->offset(20);
```

## WHERE OPERATORS
```php
->where('f')->equals($v)        ->where('f')->notEquals($v)
->where('f')->greater($v)       ->where('f')->greaterEquals($v)
->where('f')->lower($v)         ->where('f')->lowerEquals($v)
->where('f')->isNull()          ->where('f')->isNotNull()
->where('f')->like('%x%')       ->where('f')->notLike('%x%')
->where('f')->in([1,2])         ->where('f')->notIn([1,2])
->where('f')->between(10, 20)   ->where('f')->notBetween(10, 20)
```

## CONDITION CHAINING
```php
->where('a')->equals(1)
->and('b')->greater(2)          // AND
->or('c')->isNull()             // OR
->and('d', '(')->equals(3)     // AND ( open bracket
->or('e')->equals(4, ')')      // OR ... ) close bracket
```

## JOINS
```php
->innerJoin('users u', 'u.id = o.user_id')
->leftJoin('orders o', 'o.user_id = u.id')
->rightJoin('addresses a', 'a.id = u.address_id')
->fullJoin('profiles p', 'p.user_id = u.id')  // PostgreSQL only — InvalidArgumentException on MySQL/SQLite
->crossJoin('categories c')
->innerJoin($subquery, 'sq.id = t.id', 'sq')  // subquery join
```

## SUBQUERIES
```php
$sub = (new DbQuery())->select('user_id')->from('orders');
->where('id')->in($sub)          // WHERE IN subquery
->selectSubquery($sub, 'alias')  // SELECT subquery
->from($sub, 'alias')            // FROM subquery
->exists($sub)
->notExists($sub)
```

## GROUP BY / HAVING
```php
->groupBy('dept', 'status')
->having('cnt')->greater(5)->and('cnt')->lower(100)
```

## CTEs
```php
$cte = (new DbQuery())->select('dept, AVG(salary) as avg')->from('emp')->groupBy('dept');
(new DbQuery())->with('dept_avg', $cte)->select('*')->from('emp e')
    ->innerJoin('dept_avg d', 'e.dept = d.dept');
->withRecursive('tree', $recursiveCte);
```

## WINDOW FUNCTIONS
```php
// Inline
$q->selectWindow('ROW_NUMBER', 'row_num')
    ->partitionBy('department')->windowOrderBy('salary', 'DESC')
    ->frame('ROWS', 'UNBOUNDED PRECEDING', 'CURRENT ROW')
    ->endWindow()->select('name, salary')->from('employees');

// Named
$q->window('w')->partitionBy('dept')->windowOrderBy('sal', 'DESC')->endWindow();
$q->selectWindowRef('ROW_NUMBER', 'w', 'row_num');
```

## UNION
```php
$q1->union($q2);      // UNION
$q1->unionAll($q2);   // UNION ALL
```

## EXPRESSION (Raw SQL)
```php
use JardisSupport\DbQuery\Data\Expression;
->where('price')->greater(Expression::raw('cost * 1.19'))
->set('updated_at', Expression::raw('NOW()'))
->where(Expression::raw('YEAR(created)'))->equals(2024)
```

## INSERT
```php
use JardisSupport\DbQuery\DbInsert;

(new DbInsert())->into('users')->set(['name' => 'John', 'email' => 'j@x.com']);
(new DbInsert())->into('users')->fields('name', 'email')->values('John', 'j@x.com')->values('Jane', 'jane@x.com');
(new DbInsert())->into('users')->fields('name', 'email')->fromSelect($selectQuery);  // INSERT...SELECT

// Conflict handling (dialect-specific):
->onDuplicateKeyUpdate('name', 'New')              // MySQL
->onConflict('email')->doUpdate(['name' => 'New']) // PostgreSQL
->onConflict('email')->doNothing()                 // PostgreSQL
->orIgnore()                                       // SQLite
->replace()                                        // SQLite
```

## UPDATE
```php
use JardisSupport\DbQuery\DbUpdate;

(new DbUpdate())->table('users')->set('name', 'John')->setMultiple(['status' => 'active'])
    ->where('id')->equals(123);

// MySQL/MariaDB only — InvalidArgumentException on PostgreSQL/SQLite:
->table('users', 'u')->innerJoin('orders o', 'u.id=o.user_id')->set('u.status', 'premium')
->orderBy('created_at', 'DESC')->limit(10)->ignore()
```

## DELETE
```php
use JardisSupport\DbQuery\DbDelete;

(new DbDelete())->from('sessions')->where('expires_at')->lower(time());

// MySQL/MariaDB only — InvalidArgumentException on PostgreSQL/SQLite:
->from('users', 'u')->innerJoin('banned b', 'u.email=b.email')
->orderBy('created_at', 'ASC')->limit(100)
```

## JSON OPERATIONS
```php
->whereJson('settings')->extract('$.theme')->equals('dark')
->andJson('tags')->contains('php')
->orJson('meta')->extract('$.score')->greater(5)
->whereJson('data')->notContains('secret')
->whereJson('items')->length()->greater(3)
->whereJson('items')->length('$.nested')->equals(0)
->havingJson('attrs')->extract('$.priority')->greater(5)
```

## DIALECT ENUM
```php
use JardisSupport\DbQuery\Data\Dialect;
Dialect::MySQL->value;           // 'mysql'
Dialect::MariaDB->value;         // 'mariadb'
Dialect::PostgreSQL->value;      // 'postgres'
Dialect::SQLite->value;          // 'sqlite'
Dialect::MySQL->defaultVersion();            // '8.0'
Dialect::MySQL->supportsVersion('8.4');      // bool
Dialect::fromString('mysql');    // throws ValueError if invalid
Dialect::tryFromString('mysql'); // null if invalid
Dialect::values();               // ['mysql', 'mariadb', 'postgres', 'sqlite']
```

## RESULT CLASSES
```php
use JardisSupport\DbQuery\Data\{QueryResult, ExecuteResult};
$result->fetchAll();       // array<int, array<string, mixed>>
$result->fetchOne();       // ?array<string, mixed>
$result->rowCount();       // int
$result->affectedRows();   // int              (ExecuteResult only)
$result->lastInsertId();   // string|false     (ExecuteResult only)
```

## VERSION-AWARE SQL
```php
$q->sql('mysql', prepared: true, version: '8.0');
$q->sql('postgres', prepared: true, version: '16');
```

## LAYER
- **Repository / Infrastructure:** build queries; always use `prepared: true`
- **Domain:** NEVER imports DbQuery builders
