#!/bin/bash

cd /tmp/

cat > ./turnserver.conf << EOF
external-ip=${TURN_PUBLIC_IP:-127.0.0.1}
listening-port=${TURN_LISTEN_PORT:-3478}
fingerprint
lt-cred-mech
max-port=${MAX_PORT:-65535}
min-port=${MIN_PORT:-40000}
pidfile="$(pwd)/turnserver.pid"
realm=kolabmeet
log-file=stdout
redis-userdb="ip=${REDIS_IP:-127.0.0.1} dbname=${DB_NAME:-2} password=${DB_PASSWORD:-turn} connect_timeout=30"
verbose
EOF

/usr/bin/turnserver -c ./turnserver.conf
