## Quickstart Instructions

Really quick?

```
$ bin/quickstart.sh
```

More detailed:

```
$ bin/regen-certs
$ docker pull kolab/centos7:latest
$ docker-compose down
$ docker-compose up -d
$ cd src/
$ composer install
$ npm install
$ cp .env.example .env
$ echo "" >> .env
$ cat .env.local >> .env
$ ./artisan key:generate
$ ./artisan jwt:secret -f
$ ./artisan clear-compiled
$ npm run dev
$ rm -rf database/database.sqlite
$ touch database/database.sqlite
$ ./artisan migrate:refresh --seed
$ ./artisan serve
```

NOTE: Set `APP_PUBLIC_URL` and `MOLLIE_KEY` and other such private settings in `.env.local`
