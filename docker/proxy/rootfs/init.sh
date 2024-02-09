#!/bin/bash

sed -i -r \
    -e "s|APP_WEBSITE_DOMAIN|$APP_WEBSITE_DOMAIN|g" \
    -e "s|SSL_CERTIFICATE_CERT|$SSL_CERTIFICATE|g" \
    -e "s|SSL_CERTIFICATE_KEY|$SSL_CERTIFICATE_KEY|g" \
    -e "s|WEBAPP_BACKEND|$WEBAPP_BACKEND|g" \
    -e "s|MEET_BACKEND|$MEET_BACKEND|g" \
    -e "s|ROUNDCUBE_BACKEND|$ROUNDCUBE_BACKEND|g" \
    -e "s|DAV_BACKEND|$DAV_BACKEND|g" \
    -e "s|COLLABORA_BACKEND|$COLLABORA_BACKEND|g" \
    /etc/nginx/nginx.conf

exec nginx -g "daemon off;"
