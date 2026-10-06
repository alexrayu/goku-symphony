COMPOSE = docker compose --env-file versions.env
RUN     = $(COMPOSE) run --rm php

.PHONY: up down install stan schema test provision deploy

up:
	$(COMPOSE) up -d --build

down:
	$(COMPOSE) down

install:
	$(RUN) composer install

stan:
	$(RUN) sh -c "bin/console cache:warmup --env=dev && vendor/bin/phpstan analyse --memory-limit=1G"

schema:
	$(RUN) bin/console doctrine:schema:validate --skip-sync

test:
	$(RUN) vendor/bin/phpunit

# Prod. Needs ansible/secrets.yml and the inventory host. Deploy a branch: make deploy REF=<branch>
provision:
	cd ansible && ansible-playbook provision.yml

deploy:
	cd ansible && ansible-playbook deploy.yml $(if $(REF),-e app_ref=$(REF))
