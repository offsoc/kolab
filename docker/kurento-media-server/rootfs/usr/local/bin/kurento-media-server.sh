#!/bin/bash

if [ ! -z "${OPENVIDU_COTURN_IP}" ]; then
    sed -i \
        -e "s/;externalIPv4.*$/externalIPv4=${OPENVIDU_COTURN_IP}/g" \
        /etc/kurento/modules/kurento/WebRtcEndpoint.conf.ini
fi

if [ ! -z "${KMS_PORT}" ]; then
    sed -i \
        -e "s/\"port\": 8888,/\"port\": ${KMS_PORT},/g" \
        /etc/kurento/kurento.conf.json
fi

/usr/bin/kurento-media-server
