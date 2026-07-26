<---stack-------->: ## -----------------------------------------------------------------------
start: ## Start the stack (web + app) and wait until ready
	$(DOCKER_COMPOSE) up -d --wait
	@echo "✓ http://localhost:$(HTTP_PORT)"
.PHONY: start

start-full: ## Start the stack including db, cache and mail
	$(DOCKER_COMPOSE) --profile db --profile cache --profile mail up -d --wait
	@echo "✓ http://localhost:$(HTTP_PORT)   mail: http://localhost:$(MAILHOG_UI_PORT)"
.PHONY: start-full

stop: ## Stop and remove all containers of this project
	@$(DOCKER_COMPOSE) --profile db --profile cache --profile mail --profile cli down --remove-orphans
	@echo "Containers stopped and removed."
.PHONY: stop

restart: stop start ## Restart the stack
.PHONY: restart

status: ## Show container status
	@$(DOCKER_COMPOSE) --profile db --profile cache --profile mail --profile cli ps -a
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
