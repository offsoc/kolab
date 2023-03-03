#!/bin/bash
# We use port 12143 because it has plain auth enabled
echo "$@" | cyradm --auth PLAIN -u cyrus-admin -w Welcome2KolabSystems  --port 12143 localhost
