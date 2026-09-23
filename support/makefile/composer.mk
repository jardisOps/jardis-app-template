<---composer----->: ## -----------------------------------------------------------------------
install: ## Run composer install
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli composer install --no-cache
.PHONY: install

update: ## Run composer update
	$(DOCKER_COMPOSE) run --rm --no-deps -e XDEBUG_MODE=off phpcli composer update
.PHONY: update

autoload: ## Run composer dump-autoload
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli composer dumpautoload
.PHONY: autoload

# One-off `run`, not `exec` — must work even when the stack is not up (the
# Builder calls this before any container is running). composer's own exit
# code passes through docker compose run and then through make untouched, so
# a failed require (bad package name, version conflict) fails the caller too.
composer-require: ## Require a package in the phpcli container (PACKAGE=vendor/name, optional VERSION=constraint)
	@if [ -z "$(PACKAGE)" ]; then \
		echo "FEHLER: PACKAGE ist erforderlich — z. B. make composer-require PACKAGE=jardisadapter/cache"; \
		exit 1; \
	fi
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli composer require --no-interaction "$(PACKAGE)$(if $(VERSION),:$(VERSION),)"
.PHONY: composer-require
