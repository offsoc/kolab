HOSTNAME=10.10.4.5
PUBLIC_IP=212.103.80.171
PUBLIC_DOMAIN=stun-dev.kolab.io
AUTH_TOKEN="AUTHTOKEN"
TURN_STATIC_SECRET="TURNSTATICSECRET"

setup:
	touch ./hosts
	echo "${HOSTNAME}" > ./hosts
	ansible-playbook -v --inventory=./hosts --extra-vars="hostname=${HOSTNAME} public_domain=${PUBLIC_DOMAIN} public_ip=${PUBLIC_IP} auth_token=${AUTH_TOKEN} turn_static_secret=${TURN_STATIC_SECRET}" setup.yml
