# Commandes du projet. Fonctionne avec GNU Make sous Linux, macOS et Windows (cmd, PowerShell, Git Bash).
PHP     ?= php
CONSOLE  = $(PHP) bin/console

# Instantane du contenu (pages, rubriques, demarches, chiffres, reglages, menu). Valeurs du poste WAMP, a surcharger : make db-dump DB_PORT=3306
MYSQL     ?= mysql
MYSQLDUMP ?= mysqldump
DB_HOST   ?= 127.0.0.1
DB_PORT   ?= 3307
DB_USER   ?= root
DB_NAME   ?= mcm
DB_CONTENT_TABLES = page page_translation category category_translation tag tag_translation key_figure key_figure_translation service service_translation setting setting_translation menu_item menu_item_translation

.DEFAULT_GOAL := help
.PHONY: help install db db-reset db-dump db-restore dev test screenshots stan cs cs-fix build

help:
	@echo install  Dependances PHP, importmap, build Tailwind et ChromeDriver
	@echo db       Base creee si absente et migrations jouees (le contenu vit en base, rien n'est ecrase)
	@echo db-reset DESTRUCTIF : base supprimee et recreee vide (contenu a recharger avec db-restore)
	@echo db-dump  Instantane SQL du contenu dans database/contenu.sql
	@echo db-restore Recharge database/contenu.sql dans la base
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
	$(CONSOLE) doctrine:database:create --if-not-exists
	$(CONSOLE) doctrine:migrations:migrate --no-interaction

db-reset:
	$(CONSOLE) doctrine:database:drop --force --if-exists
	$(MAKE) db

db-dump:
	$(MYSQLDUMP) -h$(DB_HOST) -P$(DB_PORT) -u$(DB_USER) --no-create-info --complete-insert --skip-extended-insert --skip-comments --skip-dump-date --replace $(DB_NAME) $(DB_CONTENT_TABLES) > database/contenu.sql

db-restore:
	$(MYSQL) -h$(DB_HOST) -P$(DB_PORT) -u$(DB_USER) $(DB_NAME) < database/contenu.sql

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
