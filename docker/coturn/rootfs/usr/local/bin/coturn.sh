#!/bin/bash

cd /tmp/

cat > ./turnserver.conf << EOF
external-ip=${TURN_PUBLIC_IP:-127.0.0.1}
listening-port=${TURN_LISTEN_PORT:-3478}
fingerprint
lt-cred-mech

# Temporary for testing
user=username1:password1
allow-loopback-peers
cli-password=qwerty

# Disabled by default to avoid DoS attacks. Logs all bind attempts in verbose log mode (useful for debugging)
log-binding

max-port=${MAX_PORT:-65535}
min-port=${MIN_PORT:-40000}
pidfile="$(pwd)/turnserver.pid"
realm=kolabmeet
log-file=stdout
redis-userdb="ip=${REDIS_IP:-127.0.0.1} dbname=${REDIS_DBNAME:-2} password=${REDIS_PASSWORD:-turn} connect_timeout=30"
verbose
EOF

/usr/bin/turnserver -c ./turnserver.conf
