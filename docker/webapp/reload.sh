#!/bin/bash

if pgrep -f horizon; then
    pkill -9 -f "/usr/bin/php artisan horizon:work.*"
else
    /usr/bin/php artisan octane:reload
fi
