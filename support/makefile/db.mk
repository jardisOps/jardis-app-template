<---database---->: ## -----------------------------------------------------------------------
# One-off `run`, not `exec` — works without a running stack (SQLite default).
# SCHEMA is a command-line variable, so make exports it to the recipe shell;
# the recipe only ever reads it as "$${SCHEMA:-}" (double-quoted; the default keeps `bash -u` quiet when SCHEMA is unset), never splices
# the make expansion into the command line.
# Without SCHEMA: sets up the whole database (package SQL from vendor, then all
# BC schemas under .jardis/ for the connection's driver).
db-import: ## Set up the database (package SQL + all BC schemas); SCHEMA=<path relative to the project root> imports one file only
	$(DOCKER_COMPOSE) run --rm --no-deps -e SCHEMA phpcli php /app/bin/console db:import "$${SCHEMA:-}"
.PHONY: db-import

# Reset = drop every table and view of the project's own database, then exactly
# `db-import` without SCHEMA. Deletes ALL DATA, no confirmation here: the caller decides.
# Same one-off `run` form as db-import (SQLite default works without a stack).
db-reset: ## Delete ALL DATA (drop every table and view), then set the database up again from the schema like db-import
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli php /app/bin/console db:reset
.PHONY: db-reset

# Gives every record of a protected table that holds no access entry yet its
# entry (the generated src/Api/*/backfill.php). Same one-off `run` form as
# db-import. Safe to repeat: a second pass writes nothing.
access-backfill: ## Give every record without an access entry its entry (generated backfill.php); safe to run again
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli php /app/bin/console access:backfill
.PHONY: access-backfill
