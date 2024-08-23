build:
	podman build . -t migrate
shell:
	podman run --network=host --rm -ti -v /home/mollekopf/src/kolab/docker/migrate/input:/opt/app-root/input/ -w /opt/app-root/src migrate /bin/bash
