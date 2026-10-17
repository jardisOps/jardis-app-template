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
