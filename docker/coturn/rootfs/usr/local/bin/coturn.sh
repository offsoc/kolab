#!/bin/bash

cd /tmp/

cat > ./turnserver.conf << EOF
external-ip=${TURN_PUBLIC_IP:-127.0.0.1}
listening-port=${TURN_LISTEN_PORT:-3478}
fingerprint

# Temporary for testing
allow-loopback-peers
cli-password=qwerty

# Disabled by default to avoid DoS attacks. Logs all bind attempts in verbose log mode (useful for debugging)
log-binding

max-port=${MAX_PORT:-65535}
min-port=${MIN_PORT:-40000}
pidfile="$(pwd)/turnserver.pid"
realm=kolabmeet
log-file=stdout

# Dynamically generate username/password for turn
use-auth-secret
static-auth-secret=${TURN_STATIC_SECRET:-uzYguvIl9tpZFMuQOE78DpOi6Jc7VFSD0UAnvgMsg5n4e74MgIf6vQvbc6LWzZjz}

verbose
EOF

/usr/bin/turnserver -c ./turnserver.conf
