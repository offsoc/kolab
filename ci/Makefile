PWD=$(shell pwd)


configure:
	cd .. ; \
	bin/configure.sh config.demo ;

setup:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	cd .. && bin/quickstart.sh --nodev

build:
	cd .. && docker compose -f docker-compose.yml f docker-compose.override.yml -f docker-compose.build.yml build && cd ci

lint:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	docker run -v ${PWD}/../:/src/kolab.orig --name kolab-tests -t kolab-tests /lint.sh

test:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	docker run --network=kolab_kolab -v ${PWD}/../src:/src/kolabsrc.orig --name kolab-tests -t kolab-tests /init.sh testsuite

quicktest:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	docker run --network=kolab_kolab -v ${PWD}/../src:/src/kolabsrc.orig --name kolab-tests -t kolab-tests /init.sh quicktest

browser:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	docker run --network=kolab_kolab -v ${PWD}/../src:/src/kolabsrc.orig --name kolab-tests -t kolab-tests /init.sh suite-Browser

shell:
	docker kill kolab-tests || : ; \
	docker rm kolab-tests || : ; \
	docker run --network=kolab_kolab -v ${PWD}/../src:/src/kolabsrc.orig --name kolab-tests -ti kolab-tests /init.sh shell

all: configure build setup lint test

