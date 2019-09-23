#!/bin/bash

set -e

base_dir=$(dirname $(dirname $0))

bin/regen-certs

docker-compose down
docker-compose build
docker-compose up -d

pushd ${base_dir}/src/
#composer install
#npm install
cp .env.example .env
./artisan key:generate
./artisan jwt:secret -f
./artisan clear-compiled
#npm run dev
rm -rf database/database.sqlite
touch database/database.sqlite
./artisan migrate:refresh --seed
./artisan serve
popd
