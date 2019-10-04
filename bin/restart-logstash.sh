#!/bin/bash

docker-compose stop logstash
docker-compose up -d logstash
docker-compose logs -f logstash
