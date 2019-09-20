## Quickstart Instructions

Really quick?

```
$ bin/quickstart.sh
```

More detailed:

```
$ bin/regen-certs
$ docker-compose down
$ docker-compose up -d
$ cd src/
$ composer install
$ npm install
$ cp .env.example .env
$ ./artisan key:generate
$ ./artisan jwt:secret -f
$ npm run dev
$ ./artisan migrate:refresh --seed
$ ./artisan serve
```
