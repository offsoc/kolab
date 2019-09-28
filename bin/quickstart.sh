#!/bin/bash

set -e

base_dir=$(dirname $(dirname $0))

bin/regen-certs

docker pull kolab/centos7:latest

docker-compose down
docker-compose build

docker-compose up -d kolab mariadb redis

pushd ${base_dir}/src/
composer install
npm install
find bootstrap/cache/ -type f ! -name ".gitignore" -delete
cp .env.example .env
./artisan key:generate
./artisan jwt:secret -f
./artisan clear-compiled
./artisan cache:clear
npm run dev
popd

docker-compose up -d worker

pushd ${base_dir}/src/
rm -rf database/database.sqlite
php -dmemory_limit=512M ./artisan migrate:refresh --seed
./artisan serve
popd

