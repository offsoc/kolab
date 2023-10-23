#!/bin/sh

# (
# while true; do
# 	sa-update -v
# 	sleep 30h
# done
# ) &
# BACKGROUND_TASKS="$!"

sed -i -r \
    -e "s|APP_DOMAIN|$APP_DOMAIN|g" \
    /etc/clam.d/amavisd.conf

mkdir -p /var/run/amavisd
chmod 777 /var/run/amavisd

#/usr/bin/freshclam --quiet --datadir=/var/lib/clamav
#/usr/bin/freshclam -d -c 1

exec amavisd -c /etc/clam.d/amavisd.conf foreground
# amavisd -c /etc/clam.d/amavisd.conf foreground &
# BACKGROUND_TASKS="${BACKGROUND_TASKS} $!"

# while true; do
# 	for bg_task in ${BACKGROUND_TASKS}; do
# 		if ! kill -0 ${bg_task} 1>&2; then
# 			echo "Worker ${bg_task} died, stopping container waiting for respawn..."
# 			kill -TERM 1
# 		fi
# 		sleep 10
# 	done
# done
