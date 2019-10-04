#!/bin/bash

docker-compose stop logstash

for index in $(curl localhost:9200/_cat/indices 2>/dev/null | awk '{print $3}' | grep ^logstash)
do
    curl -X DELETE localhost:9200/${index} >/dev/null 2>&1
done

find docker/logstash/_grokparsefailures/ -type f ! -name ".gitignore" -delete

curl -X DELETE localhost:9200/_template/logstash-1.0.0 >/dev/null 2>&1

curl -X PUT -H 'Content-Type: application/json' \
    -d "$(cat docker/logstash/templates/logstash_template.json)" \
    localhost:9200/_template/logstash-1.0.0 >/dev/null 2>&1

docker-compose up -d --force-recreate logstash
docker-compose logs -f logstash
