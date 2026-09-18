VERSION_PHP=8.4
DOCKER_IMAGE = php:$(VERSION_PHP)-cli
PHP_CS_FIXER = docker run --rm -v $(PWD):/code ghcr.io/php-cs-fixer/php-cs-fixer:3.94.2-php$(VERSION_PHP)
PHP_STAN = docker run --rm -v $(PWD):/app ghcr.io/phpstan/phpstan:2.1.40-php$(VERSION_PHP)
COMPOSER = docker run --rm --user $(shell id -u):$(shell id -g) --env COMPOSER_HOME=/tmp/composer --volume $(PWD):/app --workdir /app composer:2

.DEFAULT_GOAL := help

.PHONY: help
help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

.PHONY: install
install: ## Install composer dependencies
	$(COMPOSER) install --ignore-platform-reqs --no-scripts --no-interaction

.PHONY: update
update: ## Update composer dependencies
	$(COMPOSER) update --ignore-platform-reqs --no-scripts --no-interaction

vendor:
	@$(MAKE) install

.PHONY: test
test: vendor ## Run tests
	docker run --rm --user $(shell id -u):$(shell id -g) --volume $(PWD):/app --workdir /app $(DOCKER_IMAGE) vendor/bin/phpunit --configuration phpunit.xml.dist --no-coverage

.PHONY: lint
lint: lint-php lint-php-static ## Run all linters

.PHONY: lint-fix
lint-fix: lint-php-fix ## Fix all auto-fixable lint issues

.PHONY: lint-php
lint-php: ## Lint PHP code style
	$(PHP_CS_FIXER) fix --dry-run --diff

.PHONY: lint-php-fix
lint-php-fix: ## Fix PHP code style
	$(PHP_CS_FIXER) fix -vv

.PHONY: lint-php-static
lint-php-static: vendor ## Static code analysis
	$(PHP_STAN) analyse

.PHONY: audit
audit: ## Composer audit checking for security vulnerabilities
	$(COMPOSER) audit

.PHONY: clean
clean: ## Remove generated files
	rm -rf vendor .phpunit.cache .php-cs-fixer.cache composer.lock
