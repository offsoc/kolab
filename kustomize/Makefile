apply-dev:
	oc apply -k overlays/dev/
build:
	oc start-build kolab --follow
binary-build-overlay:
	oc start-build overlay --from-dir=/path/to/overlay-directory --follow

