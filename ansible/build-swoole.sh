#!/bin/bash

yes | pecl install swoole

echo "extension=swoole.so" >> /etc/php.d/swoole.ini
php -m 2>&1 | grep -q swoole
