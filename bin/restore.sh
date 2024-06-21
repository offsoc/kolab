#!/bin/bash
backup_path="$(pwd)/backup/"

function restore_volume {
  volume_name=$1
  backup_destination=$2

  echo "Restoring $volume_name from $backup_destination"
  docker run --rm -v $volume_name:/data -v $backup_destination:/backup quay.io/centos/centos:stream8 bash -c "rm -rf /data/* && tar xvf /backup/$volume_name.tar -C /data --strip 1"
}

echo "Stopping containers"
docker compose stop

# We currently expect the volumes to exist.
# We could alternatively create volumes form existing tar files
# for f in backup/*.tar; do
#     echo "$(basename $f .tar)" ;
# done

echo "Restoring volumes"
volumes=($(docker volume ls -f name=kolab | awk '{if (NR > 1) print $2}'))
for v in "${volumes[@]}"
do
  restore_volume $v $backup_path
done
echo "Restarting containers"
docker compose start

