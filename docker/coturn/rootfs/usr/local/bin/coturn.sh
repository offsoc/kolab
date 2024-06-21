#!/bin/bash

cd /tmp/

cat > ./turnserver.conf << EOF
external-ip=${TURN_PUBLIC_IP:-127.0.0.1}
listening-port=${TURN_LISTEN_PORT:-3478}
fingerprint

max-port=${MAX_PORT:-65535}
min-port=${MIN_PORT:-40000}
pidfile="$(pwd)/turnserver.pid"
realm=kolabmeet
log-file=stdout

EOF

if $TURN_STATIC_SECRET; then
    cat >> ./turnserver.conf << EOF
# Dynamically generate username/password for turn
use-auth-secret
static-auth-secret=${TURN_STATIC_SECRET}

EOF
fi

if $DEBUG; then
    cat >> ./turnserver.conf << EOF
# For testing
allow-loopback-peers
cli-password=simple123

# Disabled by default to avoid DoS attacks. Logs all bind attempts in verbose log mode (useful for debugging)
log-binding

verbose

EOF
fi

/usr/bin/turnserver -c ./turnserver.conf
