#!/bin/bash

dnf -y install php-xdebug

cat << EOF > /etc/php.d/xdebug.ini
zend_extension=/usr/lib64/php/modules/xdebug.so

# Profiler config for xdebug3
xdebug.mode=profile
xdebug.output_dir="/tmp/"
xdebug.start_with_request=trigger

EOF

cat << EOF > /etc/php-fpm.d/xdebug.ini
zend_extension=/usr/lib64/php/modules/xdebug.so

# Profiler config for xdebug3
xdebug.mode=profile
xdebug.output_dir="/tmp/"
xdebug.start_with_request=trigger

EOF

killall php-fpm
/usr/sbin/php-fpm
