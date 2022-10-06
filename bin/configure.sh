#!/bin/bash

# Uninstall the old config
if [ -d config ]; then
    echo "Uninstalling the old config."
    find -L config/ -type f | while read file; do
        file=$(echo $file | sed -e 's|^config||g')
        file="./$file"

        rm -v $file
    done
fi

if [ "$1" == "" ]; then
    echo "Failed to find the configuration folder, please pass one as argument (e.g. config.demo)."
    exit 1
fi

if [ ! -d $1 ]; then
    echo "Failed to find the configuration folder, please pass one as argument (e.g. config.demo)."
    exit 1
fi

echo "Installing $1."
# Link new config
rm config
ln -s $1 config

# Install new config
find -L config/ -type f | while read file; do
    dir=$(dirname $file | sed -e 's|^config||g')
    dir="./$dir"

    if [ ! -d $dir ]; then
        mkdir -p $dir
    fi

    cp -v $file $dir/
done


# Generate random secrets
if ! grep -q "COTURN_STATIC_SECRET" .env; then
    COTURN_STATIC_SECRET=$(openssl rand -hex 32);
    echo "COTURN_STATIC_SECRET=${COTURN_STATIC_SECRET}" >> src/.env
fi

if ! grep -q "MEET_WEBHOOK_TOKEN" .env; then
    MEET_WEBHOOK_TOKEN=$(openssl rand -hex 32);
    echo "MEET_WEBHOOK_TOKEN=${MEET_WEBHOOK_TOKEN}" >> src/.env
fi

if ! grep -q "MEET_SERVER_TOKEN" .env; then
    MEET_SERVER_TOKEN=$(openssl rand -hex 32);
    echo "MEET_SERVER_TOKEN=${MEET_SERVER_TOKEN}" >> src/.env
fi

# Customize configuration
sed -i \
    -e "s/{{ host }}/${HOSTNAME}/g" \
    -e "s/{{ openexchangerates_api_key }}/${OPENEXCHANGERATES_API_KEY}/g" \
    -e "s/{{ firebase_api_key }}/${FIREBASE_API_KEY}/g" \
    -e "s/{{ public_ip }}/${PUBLIC_IP}/g" \
    -e "s/{{ admin_password }}/${ADMIN_PASSWORD}/g" \
    src/.env

if [ -f /etc/letsencrypt/live/${HOSTNAME}/cert.pem ]; then
    echo "Using the available letsencrypt certificate for ${HOSTNAME}"
    cat >> .env << EOF
KOLAB_SSL_CERTIFICATE=/etc/letsencrypt/live/${HOSTNAME}/cert.pem
KOLAB_SSL_CERTIFICATE_FULLCHAIN=/etc/letsencrypt/live/${HOSTNAME}/fullchain.pem
KOLAB_SSL_CERTIFICATE_KEY=/etc/letsencrypt/live/${HOSTNAME}/privkey.pem
PROXY_SSL_CERTIFICATE=/etc/letsencrypt/live/${HOSTNAME}/fullchain.pem
PROXY_SSL_CERTIFICATE_KEY=/etc/letsencrypt/live/${HOSTNAME}/privkey.pem
EOF
fi
