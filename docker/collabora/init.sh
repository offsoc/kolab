#!/bin/sh


GROUPSSTRING=""
for HOST in $ALLOWED_HOSTS; do
    GROUPSSTRING="$GROUPSSTRING<group><host desc=\"hostname to allow or deny.\" allow=\"true\">$HOST</host></group>\n"
done

sed -i -e "s|ALLOWED_HOSTS_GROUPS|$GROUPSSTRING|" /etc/coolwsd/coolwsd.xml

mkdir -p /tmp/ssl/
pushd /tmp/ssl/
mkdir -p certs/ca
openssl rand -writerand /opt/cool/.rnd
openssl genrsa -out certs/ca/root.key.pem 2048
openssl req -x509 -new -nodes -key certs/ca/root.key.pem -days 9131 -out certs/ca/root.crt.pem -subj "/C=DE/ST=BW/L=Stuttgart/O=Dummy Authority/CN=Dummy Authority"
mkdir -p certs/tmp
mkdir -p certs/servers/localhost
openssl genrsa -out certs/servers/localhost/privkey.pem 2048
if test "${cert_domain-set}" = set; then
    openssl req -key certs/servers/localhost/privkey.pem -new -sha256 -out certs/tmp/localhost.csr.pem -subj "/C=DE/ST=BW/L=Stuttgart/O=Dummy Authority/CN=localhost"
else
    openssl req -key certs/servers/localhost/privkey.pem -new -sha256 -out certs/tmp/localhost.csr.pem -subj "/C=DE/ST=BW/L=Stuttgart/O=Dummy Authority/CN=${cert_domain}"
fi
openssl x509 -req -in certs/tmp/localhost.csr.pem -CA certs/ca/root.crt.pem -CAkey certs/ca/root.key.pem -CAcreateserial -out certs/servers/localhost/cert.pem -days 9131
mv -f certs/servers/localhost/privkey.pem /etc/coolwsd/key.pem
mv -f certs/servers/localhost/cert.pem /etc/coolwsd/cert.pem
mv -f certs/ca/root.crt.pem /etc/coolwsd/ca-chain.cert.pem
popd

exec /usr/bin/coolwsd --version --o:sys_template_path=/opt/cool/systemplate --o:child_root_path=/opt/cool/child-roots --o:file_server_root_path=/usr/share/coolwsd --o:logging.color=false --o:stop_on_config_change=true
