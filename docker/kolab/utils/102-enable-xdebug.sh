#!/bin/bash

yum -y install php-xdebug

cat << EOF > /etc/php.d/xdebug.ini
zend_extension=/usr/lib64/php/modules/xdebug.so

# Profiler config for xdebug3
#xdebug.mode=profile
#xdebug.output_dir="/tmp/"
#xdebug.start_with_request=trigger

# Profiler config for xdebug2
#xdebug.remote_log="/tmp/xdebug.log"
xdebug.profiler_enable = 0
# Enable using a XDEBUG_PROFILE GET/POST parameter
xdebug.profiler_enable_trigger = 1
xdebug.profiler_output_dir = "/tmp/"
#xdebug.remote_enable=on
#xdebug.remote_port=9000
#xdebug.remote_autostart=0
#xdebug.remote_connect_back=on
#xdebug.idekey=editor-xdebug
EOF
