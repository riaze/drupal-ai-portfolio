# Task runner for the monorepo.
#
# Locally every PHP/Drush command runs inside DDEV (drupal/.ddev), so the PHP
# version always matches production. In CI (CI=true, set by GitHub Actions)
# the same targets run natively against the PHP set up by the workflow.

SHELL := /bin/bash
.SHELLFLAGS := -eu -o pipefail -c
.DEFAULT_GOAL := help

DRUPAL_DIR := drupal

ifeq ($(CI),true)
  EXEC  := cd $(DRUPAL_DIR) &&
  DRUSH := cd $(DRUPAL_DIR) && vendor/bin/drush
else
  EXEC  := cd $(DRUPAL_DIR) && ddev exec
  DRUSH := cd $(DRUPAL_DIR) && ddev drush
endif

.PHONY: help setup start stop lint test import-content export-config config-check secrets-scan hooks lighthouse

help: ## List the available targets
	@grep -E '^[a-zA-Z_-]+:.*?## ' $(MAKEFILE_LIST) | sort | \
	  awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

setup: ## First run: start DDEV, install dependencies, install the site from config, import content
	cd $(DRUPAL_DIR) && ddev start
	cd $(DRUPAL_DIR) && ddev composer install
	@if cd $(DRUPAL_DIR) && ddev drush status --field=bootstrap 2>/dev/null | grep -q Successful; then \
	  echo "Site already installed: applying code and config (drush deploy)"; \
	  cd $(DRUPAL_DIR) && ddev drush deploy -y; \
	else \
	  echo "Installing the site from drupal/config/sync"; \
	  cd $(DRUPAL_DIR) && ddev drush site:install --existing-config --account-name=admin -y; \
	fi
	$(MAKE) import-content
	@cd $(DRUPAL_DIR) && ddev drush user:login --no-browser

start: ## Start the local environment
	cd $(DRUPAL_DIR) && ddev start

stop: ## Stop the local environment
	cd $(DRUPAL_DIR) && ddev stop

lint: ## composer validate, PHPCS (Drupal standard) and PHPStan
	$(EXEC) composer validate --strict --no-check-publish
	$(EXEC) vendor/bin/phpcs
	$(EXEC) vendor/bin/phpstan analyse --no-progress --memory-limit=1G

test: ## PHPUnit: unit and kernel tests of custom code
	$(EXEC) vendor/bin/phpunit --testsuite=custom

import-content: ## Import the Markdown files in content/ (FR and EN), idempotent
	$(DRUSH) portfolio:import-content

export-config: ## Export the active configuration to drupal/config/sync
	$(DRUSH) config:export -y

config-check: ## Fail if the active configuration differs from drupal/config/sync
	$(EXEC) scripts/config-check.sh

secrets-scan: ## Scan the whole Git history for secrets (needs gitleaks on the host)
	gitleaks git --redact --verbose .

lighthouse: ## Lighthouse scores of key pages (needs Node.js and Chrome on the host)
	scripts/lighthouse.sh

hooks: ## Install the Git hooks (pre-commit and commit-msg)
	pre-commit install --hook-type pre-commit --hook-type commit-msg
