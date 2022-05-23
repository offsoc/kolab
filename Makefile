quickstart:
	bin/quickstart.sh
	src/artisan user:assign-sku john@kolab.org beta
deploy:
	bin/deploy.sh
octane:
	src/artisan octane:start --host=(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
httprestart:
	src/artisan octane:stop || true
	src/artisan octane:start --watch --host=$$(grep OCTANE_HTTP_HOST .env | tail -n1 | sed "s/OCTANE_HTTP_HOST=//")
taillog:
	tail -f $$(ls src/storage/logs/laravel-* | sort | tail -1)
lint:
	src/phpcs
artisan:
	docker exec -ti -w /src/kolabsrc/ kolab-webapp ./artisan
mysql:
	docker exec -ti kolab-mariadb /bin/bash -c "mysql -h 127.0.0.1 -u root --password=Welcome2KolabSystems"
refresh-meet:
	docker exec -ti kolab-meet /bin/bash -c "/bin/cp -rf /src/meet/* /src/meetsrc/"
refresh-webapp:
	docker exec -ti kolab-webapp /bin/bash -c "/bin/cp -rf /src/kolabsrc.orig/* /src/kolabsrc/"
	docker exec -ti -w /src/kolabsrc/ kolab-webapp ./artisan octane:reload
