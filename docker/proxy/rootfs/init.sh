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
    -e "s|WEBMAIL_PATH|$WEBMAIL_PATH|g" \
    -e "s|SIEVE_BACKEND|$SIEVE_BACKEND|g" \
    -e "s|MATRIX_BACKEND|$MATRIX_BACKEND|g" \
    -e "s|ELEMENT_BACKEND|$ELEMENT_BACKEND|g" \
    /etc/nginx/nginx.conf

if [[ $1 == "validate" ]]; then
    exec nginx -t
else
    exec nginx -g "daemon off;"
fi
