#!/bin/bash

sed -i -r \
    -e "s|APP_WEBSITE_DOMAIN|$APP_WEBSITE_DOMAIN|g" \
    -e "s|SSL_CERTIFICATE_CERT|$SSL_CERTIFICATE|g" \
    -e "s|SSL_CERTIFICATE_KEY|$SSL_CERTIFICATE_KEY|g" \
    /etc/nginx/nginx.conf

exec nginx -g "daemon off;"
