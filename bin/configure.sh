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

if [ -f config.secrets ]; then
    # Add local secrets
    echo "" >> src/.env
    cat config.secrets >> src/.env
fi

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

if ! grep -q "APP_KEY=base64:" .env; then
    APP_KEY=$(openssl rand -base64 32);
    echo "APP_KEY=base64:${APP_KEY}" >> src/.env
fi

if ! grep -q "PASSPORT_PROXY_OAUTH_CLIENT_ID=" .env; then
    PASSPORT_PROXY_OAUTH_CLIENT_ID=$(uuidgen);
    echo "PASSPORT_PROXY_OAUTH_CLIENT_ID=${PASSPORT_PROXY_OAUTH_CLIENT_ID}" >> src/.env
fi

if ! grep -q "PASSPORT_PROXY_OAUTH_CLIENT_SECRET=" .env; then
    PASSPORT_PROXY_OAUTH_CLIENT_SECRET=$(openssl rand -base64 32);
    echo "PASSPORT_PROXY_OAUTH_CLIENT_SECRET=${PASSPORT_PROXY_OAUTH_CLIENT_SECRET}" >> src/.env
fi

if ! grep -q "PASSPORT_PUBLIC_KEY=|PASSPORT_PRIVATE_KEY=" .env; then
    PASSPORT_PRIVATE_KEY=$(openssl genrsa 4096);
    echo "PASSPORT_PRIVATE_KEY=\"${PASSPORT_PRIVATE_KEY}\"" >> src/.env

    PASSPORT_PUBLIC_KEY=$(echo "$PASSPORT_PRIVATE_KEY" | openssl rsa -pubout 2>/dev/null)
    echo "PASSPORT_PUBLIC_KEY=\"${PASSPORT_PUBLIC_KEY}\"" >> src/.env
fi

if ! grep -q "DES_KEY=" .env; then
    DES_KEY=$(openssl rand -base64 24);
    echo "DES_KEY=${DES_KEY}" >> src/.env
fi

bin/update-git-refs.sh

# Customize configuration
sed -i \
    -e "s/{{ host }}/${HOST:-kolab.local}/g" \
    -e "s/{{ openexchangerates_api_key }}/${OPENEXCHANGERATES_API_KEY}/g" \
    -e "s/{{ firebase_api_key }}/${FIREBASE_API_KEY}/g" \
    -e "s/{{ public_ip }}/${PUBLIC_IP:-172.18.0.1}/g" \
    -e "s/{{ admin_password }}/${ADMIN_PASSWORD}/g" \
    src/.env

if [ -f /etc/letsencrypt/live/${HOST}/cert.pem ]; then
    echo "Using the available letsencrypt certificate for ${HOST}"
    cat >> .env << EOF
KOLAB_SSL_CERTIFICATE=/etc/letsencrypt/live/${HOST}/cert.pem
KOLAB_SSL_CERTIFICATE_FULLCHAIN=/etc/letsencrypt/live/${HOST}/fullchain.pem
KOLAB_SSL_CERTIFICATE_KEY=/etc/letsencrypt/live/${HOST}/privkey.pem
PROXY_SSL_CERTIFICATE=/etc/letsencrypt/live/${HOST}/fullchain.pem
PROXY_SSL_CERTIFICATE_KEY=/etc/letsencrypt/live/${HOST}/privkey.pem
EOF
fi
