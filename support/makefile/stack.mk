<---stack-------->: ## -----------------------------------------------------------------------
start: ## Start the stack (web + app + COMPOSE_PROFILES from .env) and wait until ready
	$(DOCKER_COMPOSE) up -d --wait
	@echo "✓ http://localhost:$(HTTP_PORT)"
.PHONY: start

start-full: ## Start the stack including db (MariaDB), cache and mail
	$(DOCKER_COMPOSE) --profile db-mariadb --profile cache --profile mail up -d --wait
	@echo "✓ http://localhost:$(HTTP_PORT)   mail: http://localhost:$(MAILPIT_UI_PORT)"
.PHONY: start-full

stop: ## Stop and remove all containers of this project
	@$(DOCKER_COMPOSE) --profile "*" down --remove-orphans
	@echo "Containers stopped and removed."
.PHONY: stop

restart: stop start ## Restart the stack
.PHONY: restart

# Sequential on purpose: db-mariadb and db-postgres share the network alias
# "db" and must never run at once. The worker probe runs the real
# WORKER_COMMAND once, so it needs a prior `make install`. Keep this list in
# sync with the profiles in support/docker-compose.yml.
PROBE_PROFILES := db-mariadb db-postgres cache rabbitmq kafka mail

profiles-check: ## Probe every opt-in profile once (start, wait healthy, remove) — needs `make install` for the worker
	@for p in $(PROBE_PROFILES); do \
		echo "→ $$p"; \
		$(DOCKER_COMPOSE) --profile $$p up -d --wait $$p; \
		$(DOCKER_COMPOSE) --profile $$p rm -sf $$p; \
	done
	@echo "→ worker"
	@$(DOCKER_COMPOSE) run --rm --no-deps worker
	@echo "→ cli"
	@$(DOCKER_COMPOSE) run --rm --no-deps phpcli php -v
	@echo "✓ all opt-in profiles start"
.PHONY: profiles-check

status: ## Show container status
	@$(DOCKER_COMPOSE) --profile "*" ps -a
.PHONY: status

logs: ## Follow the logs of all running containers
	$(DOCKER_COMPOSE) logs -f
.PHONY: logs

<---app---------->: ## -----------------------------------------------------------------------
console: ## Run the CLI entry point (ARGS=...)
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli php /app/bin/console $(ARGS)
.PHONY: console

app-logs: ## Follow the fpm log only
	$(DOCKER_COMPOSE) logs -f app
.PHONY: app-logs

app-shell: ## Shell inside the running fpm container
	$(DOCKER_COMPOSE) exec app sh
.PHONY: app-shell
