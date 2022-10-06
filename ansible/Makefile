OX_API_KEY=
FIREBASE_API_KEY=
HOSTNAME=
PUBLIC_IP=
ADMIN_PASSWORD=
GIT_BRANCH=beta-release

setup:
	touch ./hosts
	echo "${HOSTNAME}" > ./hosts
	ansible-playbook -v --inventory=./hosts --extra-vars="hostname=${HOSTNAME} openexchangerates_api_key=${OX_API_KEY} firebase_api_key=${FIREBASE_API_KEY} public_ip=${PUBLIC_IP} admin_password=${ADMIN_PASSWORD} git_branch=${GIT_BRANCH}" setup.yml
