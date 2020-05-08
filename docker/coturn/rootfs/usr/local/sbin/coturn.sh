#!/bin/bash

cat > /etc/coturn/turnserver.conf << EOF
external-ip=${TURN_PUBLIC_IP:-127.0.0.1}
listening-port=${TURN_LISTEN_PORT:-3478}
fingerprint
lt-cred-mech
max-port=${MAX_PORT:-65535}
min-port=${MIN_PORT:-40000}
pidfile="/run/coturn/turnserver.pid"
user=openvidu:openvidu
realm=openvidu
simple-log
redis-userdb="ip=${REDIS_IP:-127.0.0.1} dbname=${DB_NAME:-2} connect_timeout=30"
verbose
EOF
