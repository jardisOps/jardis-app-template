<---database---->: ## -----------------------------------------------------------------------
# One-off `run`, not `exec` — works without a running stack (SQLite default).
# SCHEMA is a command-line variable, so make exports it to the recipe shell;
# the recipe only ever reads it as "$$SCHEMA" (double-quoted), never splices
# the make expansion into the command line.
db-import: ## Import a schema file into the configured database (SCHEMA=<path relative to the project root>)
	@if [ -z "$$SCHEMA" ]; then \
		echo "FEHLER: SCHEMA ist erforderlich — z. B. make db-import SCHEMA=.jardis/<Domain>/<Subdomain>/<BC>/Schema.mariadb.sql"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) run --rm --no-deps -e SCHEMA phpcli php /app/bin/console db:import "$$SCHEMA"
.PHONY: db-import
