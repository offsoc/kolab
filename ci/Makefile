HOSTNAME=ci.local
PUBLIC_IP=127.0.0.1
OPENEXCHANGERATES_API_KEY=dummy
FIREBASE_API_KEY=dummy
PWD=$(shell pwd)


configure:
	cd .. ; \
	cp ci/env.local src/env.local ; \
	sed -i 's/{{ host }}/${HOSTNAME}/g' src/env.local ; \
	sed -i 's/{{ public_ip }}/${PUBLIC_IP}/g' src/env.local ; \
	sed -i 's/{{ openexchangerates_api_key }}/${OPENEXCHANGERATES_API_KEY}/g' src/env.local ; \
	sed -i 's/{{ firebase_api_key }}/${FIREBASE_API_KEY}/g' src/env.local ;

setup:
	cd .. && bin/quickstart.sh --nodev

build:
	cd .. && DOCKER_BUILDKIT=0 docker compose build swoole && DOCKER_BUILDKIT=0 docker compose -f docker-compose.yml -f docker-compose.build.yml build tests && cd ci

lint:
	docker run -v ${PWD}/../:/src/kolab.orig -t kolab-tests /lint.sh

test:
	docker run --network=container:kolab -v ${PWD}/../src:/src/kolabsrc.orig -t kolab-tests /init.sh

all: configure setup build lint test

