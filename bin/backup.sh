#!/bin/bash
mkdir -p backup

backup_path="$(pwd)/backup/"

function backup_volume {
  volume_name=$1
  backup_destination=$2

  echo "Backing up $volume_name to $backup_destination"
  docker run --rm -v $volume_name:/data -v $backup_destination:/backup quay.io/centos/centos:stream8 tar -zcvf /backup/$volume_name.tar /data
}

echo "Stopping containers"
docker-compose stop

echo "Backing up volumes"
volumes=($(docker volume ls -f name=kolab | awk '{if (NR > 1) print $2}'))
for v in "${volumes[@]}"
do
  backup_volume $v $backup_path
done

echo "Restarting containers"
docker-compose start
