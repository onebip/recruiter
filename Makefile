.RECIPEPREFIX =
.DEFAULT_GOAL := help

COMPOSER :=$(shell which composer | grep -o composer)

.PHONY: help
help:
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-30s\033[0m %s\n", $$1, $$2}'

.PHONY: composer
composer: ## Composer install task
ifeq ($(COMPOSER),composer)
	@COMPOSER_ALLOW_SUPERUSER=1 composer install --no-scripts
else
	@echo 'composer not found!'
endif

.PHONY: test
test: composer phpunit ## Test task: composer, phpunit

.PHONY: phpunit
phpunit: ## PHPUnit
	@COMPOSER_ALLOW_SUPERUSER=1 ./vendor/bin/phpunit --exclude-group long spec

.PHONY: composer-upgrade
composer-upgrade:
ifeq ($(COMPOSER),composer)
	@COMPOSER_ALLOW_SUPERUSER=1 composer upgrade --no-scripts
else
	@echo 'composer not found!'
endif
