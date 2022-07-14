#!/bin/bash
bin/quickstart.sh --nodev

docker exec -w /src/kolabsrc/ kolab-webapp ./artisan user:assign john@kolab.org beta
