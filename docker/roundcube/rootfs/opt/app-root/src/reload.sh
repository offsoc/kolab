#!/bin/bash

# Reload apache
kill -SIGUSR1 1

# Reload fpm
kill -s USR2 $(pgrep "php-fpm" | head -1)
