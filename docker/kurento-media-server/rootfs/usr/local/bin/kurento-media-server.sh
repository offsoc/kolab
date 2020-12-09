#!/bin/bash

if [ ! -z "${OPENVIDU_COTURN_IP}" ]; then
    sed -i \
        -e "s/;externalIPv4.*$/externalIPv4=${OPENVIDU_COTURN_IP}/g" \
        /etc/kurento/modules/kurento/WebRtcEndpoint.conf.ini
fi

/usr/bin/kurento-media-server
