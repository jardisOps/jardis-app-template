# Otherwise identical to the module in devops/provisioning and
# devops/orchestration. One deviation: the integration-test help text named
# tests/fixtures/<provider>/, which does not exist here — and a template
# passes every wrong line on to the projects derived from it.
<---qa tools----->: ## -----------------------------------------------------------------------
phpunit: ## Run unit tests
	$(DOCKER_COMPOSE) run --rm phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests/Unit
.PHONY: phpunit

phpunit-reports: ## Run unit tests with reports
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests/Unit --coverage-clover tests/reports/clover.xml --coverage-xml tests/reports/coverage-xml
.PHONY: phpunit-reports

phpunit-coverage: ## Run unit tests with coverage text
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests/Unit --coverage-text
.PHONY: phpunit-coverage

phpunit-coverage-html: ## Run unit tests with HTML coverage
	$(DOCKER_COMPOSE) run --rm -e PCOV_ENABLED=1 phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests/Unit --coverage-html tests/reports/coverage-html
.PHONY: phpunit-coverage-html

integration-test: ## Run integration tests (requires a running stack, see make start-full)
	$(DOCKER_COMPOSE) run --rm phpcli vendor/bin/phpunit --bootstrap ./tests/bootstrap.php /app/tests/Integration
.PHONY: integration-test

# No path argument here — it would override the paths from phpstan.neon,
# which is where they are maintained.
phpstan: ## Run PHPStan analysis
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpstan analyse -c phpstan.neon
.PHONY: phpstan

# public/ as well as src/: the front controller is shipped code. bin/console
# stays out — phpcs filters by file extension and silently skips a file
# without one. PHPStan does cover it.
phpcs: ## Run coding standards
	$(DOCKER_COMPOSE) run --rm --no-deps phpcli vendor/bin/phpcs /app/src /app/public
.PHONY: phpcs
