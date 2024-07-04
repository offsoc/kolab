#!/bin/bash
# This is how to run the ci container locally
# It needs privileges because it will run the podman containers inside the container (either via kolabctl or testctl)
# Mostly useful to make sure this works as expected.

podman build docker/ci -t ci
mkdir /tmp/cicache
podman run --privileged --rm -ti -v /tmp/cicache:/var/lib/containers -e ROLE=test -e GIT_REF=dev/mollekopf ci:latest /init.sh
