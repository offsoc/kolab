APP_DOMAIN=$(shell grep APP_DOMAIN .env | tail -n1 | sed "s/APP_DOMAIN=//")
DB_ROOT_PASSWORD=$(shell grep DB_ROOT_PASSWORD .env | tail -n1 | sed "s/DB_ROOT_PASSWORD=//")
OCTANE_HTTP_HOST=$(shell grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
ADMIN_PASSWORD="simple123"

demo:
	bin/configure.sh config.demo
	bin/quickstart.sh
	src/artisan user:assign-sku john@kolab.org beta
prod:
	env KOLAB_GIT_REF=dev/mollekopf HOST=kolab.local ADMIN_PASSWORD="simple123" bin/configure.sh config.prod
	env ADMIN_PASSWORD="simple123" bin/deploy.sh
dev:
	env KOLAB_GIT_REF=dev/mollekopf HOST=kolab.local ADMIN_PASSWORD="simple123" bin/configure.sh config.docker-dev
	env ADMIN_PASSWORD=$(ADMIN_PASSWORD) bin/deploy.sh
quickstart:
	bin/quickstart.sh
remote-deploy:
	cd ansible && ./run.sh
deploy:
	env ADMIN_PASSWORD="simple123" bin/deploy.sh
build:
	docker compose build --progress=plain $1
octane:
	src/artisan octane:start --host=$(OCTANE_HTTP_HOST)
httprestart:
	src/artisan octane:stop || true
	src/artisan octane:start --watch --host=$(OCTANE_HTTP_HOST)
taillog:
	tail -f $$(ls src/storage/logs/laravel-* | sort | tail -1)
lint:
	src/phpcs
shell:
	docker exec -ti kolab-webapp /bin/bash

cyradm:
	docker compose exec -ti imap cyradm --auth PLAIN -u cyrus-admin -w simple123  --port 11143 localhost
cyradm-user:
	docker compose exec -ti imap cyradm --auth PLAIN -u admin@kolab.local -w simple123  --port 11143 localhost
mboxlist:
	docker compose exec -ti imap ctl_mboxlist -d
db:
	docker exec -ti kolab-mariadb /bin/bash -c "mysql -h 127.0.0.1 -u root --password=$(DB_ROOT_PASSWORD) kolabdev"
refresh-meet:
	docker exec -ti kolab-meet /bin/bash -c "/bin/cp -rf /src/meet/* /src/meetsrc/"
refresh-webapp:
	docker exec -ti kolab-webapp /update.sh
refresh-roundcube:
	docker exec -ti kolab-roundcube ./update-from-source.sh
ci-shell:
	cd ci && make shell
auth-check:
	echo $(APP_DOMAIN)
	docker compose exec postfix testsaslauthd -u admin@$(APP_DOMAIN) -p $(ADMIN_PASSWORD)
	docker compose exec imap testsaslauthd -u admin@$(APP_DOMAIN) -p $(ADMIN_PASSWORD)
mail-check:
	~/src/kolab/utils/mailtransporttest.py --sender-username admin@$(APP_DOMAIN) --sender-password $(ADMIN_PASSWORD) --sender-host $(APP_DOMAIN) --recipient-username admin@$(APP_DOMAIN) --recipient-password $(ADMIN_PASSWORD) --recipient-host $(APP_DOMAIN)
endpoint-check:
	~/src/kolab/utils/kolabendpointtester.py --verbose --host $(APP_DOMAIN) --dav https://$(APP_DOMAIN)/dav/ --imap $(APP_DOMAIN) --activesync $(APP_DOMAIN)  --user admin@$(APP_DOMAIN) --password $(ADMIN_PASSWORD)
deployment-check: auth-check mail-check endpoint-check
