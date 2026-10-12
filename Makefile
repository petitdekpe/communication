# Commandes du projet. Fonctionne avec GNU Make sous Linux, macOS et Windows (cmd, PowerShell, Git Bash).
PHP     ?= php
CONSOLE  = $(PHP) bin/console

.DEFAULT_GOAL := help
.PHONY: help install db dev test screenshots stan cs cs-fix build

help:
	@echo install  Dependances PHP, importmap, build Tailwind et ChromeDriver
	@echo db       Base recreee, migrations jouees, fixtures chargees
	@echo dev      Serveur local et watcher Tailwind
	@echo test     Tests PHPUnit et Panther, axe-core inclus
	@echo screenshots Captures de l'accueil dans var/screenshots
	@echo stan     PHPStan niveau 6
	@echo cs       Verifie le style sans modifier
	@echo cs-fix   Corrige le style
	@echo build    Assets de production dans public/assets

install:
	composer install
	$(CONSOLE) importmap:install
	$(CONSOLE) tailwind:build
	$(PHP) vendor/bin/bdi detect drivers

db:
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(CONSOLE) doctrine:database:create
	$(CONSOLE) doctrine:migrations:migrate --no-interaction
	$(CONSOLE) doctrine:fixtures:load --no-interaction

dev:
	symfony serve

test:
	$(CONSOLE) tailwind:build --quiet
	$(PHP) bin/phpunit

screenshots:
	$(CONSOLE) tailwind:build --quiet
	$(PHP) bin/phpunit --filter HomeScreenshotTest

stan:
	$(CONSOLE) cache:warmup --env=dev --quiet
	$(PHP) vendor/bin/phpstan analyse --memory-limit=512M --no-progress

cs:
	$(PHP) vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(PHP) vendor/bin/php-cs-fixer fix

build:
	$(CONSOLE) tailwind:build --minify --env=prod
	$(CONSOLE) asset-map:compile --env=prod
