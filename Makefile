VERSION_PHP=8.4
DOCKER_IMAGE = php:$(VERSION_PHP)-cli
PHP_CS_FIXER = docker run --rm -v $(PWD):/code ghcr.io/php-cs-fixer/php-cs-fixer:3.94.2-php$(VERSION_PHP)
PHP_STAN = docker run --rm -v $(PWD):/app ghcr.io/phpstan/phpstan:2.1.40-php$(VERSION_PHP)
COMPOSER = docker run --rm --user $(shell id -u):$(shell id -g) --env COMPOSER_HOME=/tmp/composer --volume $(PWD):/app --workdir /app composer:2

# Local settings without a git change: `.env.local` (git-ignored) - DEMO_PORT, DEMO_SKELETON,
# DEMO_PIMCORE_IMAGE and, for Pimcore 12.3 / 2026, the product-key trio.
-include .env.local
export $(shell sed -n 's/^[[:space:]]*\([A-Za-z_][A-Za-z0-9_]*\)=.*/\1/p' .env.local 2>/dev/null)

DEMO_PORT ?= 8085
DEMO := docker compose -p health-check-demo --profile demo

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

# ── Local demo ──────────────────────────────────────────────────────────────────────────────────────
# This is a bundle, so "the project" is a throwaway Pimcore app: see docker-compose.yaml and
# .docker/demo/run.sh. The first start provisions it (~5 min), later starts reuse the volume.

.PHONY: start
start: ## Bring up the local demo (Pimcore 11 + this working tree) and wait until it answers
	$(DEMO) up -d demo-app
	@echo "waiting for the demo (first run provisions Pimcore - make demo-logs to watch)"
	@i=0; while [ $$i -lt 240 ]; do \
		code=$$($(DEMO) exec -T demo-app sh -c "curl -s -o /dev/null -w '%{http_code}' http://localhost:8081/health-check-live" 2>/dev/null || echo 000); \
		[ "$$code" = 200 ] && break; \
		if [ -z "$$($(DEMO) ps -q --status running demo-app 2>/dev/null)" ]; then \
			echo "the demo exited during provisioning - its last lines:"; $(DEMO) logs --tail 40 demo-app; exit 1; \
		fi; \
		i=$$((i+1)); [ $$((i % 12)) -eq 0 ] && echo "  ... still waiting (HTTP $$code)"; sleep 5; \
	done; [ "$$code" = 200 ] || { echo "the demo never answered - run: make demo-logs"; exit 1; }
	@echo ""
	@echo "  dashboard   http://localhost:$(DEMO_PORT)/health-check   (log in at /admin first)"
	@echo "  admin       http://localhost:$(DEMO_PORT)/admin           admin / admin"
	@echo "  monitoring  http://localhost:$(DEMO_PORT)/health-check-status"
	@echo "  api         curl -H 'X-Health-Check-Api-Key: demo-api-key-0123456789abcdef' http://localhost:$(DEMO_PORT)/health-check/api"

.PHONY: stop
stop: ## Stop the demo, keeping its containers and volumes
	$(DEMO) stop

.PHONY: down
down: ## Stop and remove the demo containers, keeping the provisioned volumes (next start is fast)
	$(DEMO) down

.PHONY: destroy
destroy: ## Remove the demo containers AND volumes (next start provisions from scratch)
	$(DEMO) down -v

.PHONY: demo-audit
demo-audit: ## Re-run the audit in the demo, so the dashboard shows the current working tree
	$(DEMO) exec -T demo-app bin/console basilicom:health-check:audit

.PHONY: demo-logs
demo-logs: ## Tail the demo log (provisioning progress lives here)
	$(DEMO) logs -f demo-app

.PHONY: demo-shell
demo-shell: ## Open a shell in the demo (bin/console lives in /app)
	$(DEMO) exec demo-app bash

.PHONY: clean
clean: ## Remove generated files
	rm -rf vendor .phpunit.cache .php-cs-fixer.cache composer.lock
