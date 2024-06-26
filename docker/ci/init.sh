#!/bin/bash
set -e


cat <<EOF >> /etc/containers/registries.conf
[[registry]]
prefix = "$CACHE_REGISTRY"
insecure = true
location = "$CACHE_REGISTRY"
EOF


function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1"
        pushd "$1"
        git checkout "$3" 
        popd
    fi
}

checkout kolab $GIT_REMOTE $GIT_REF
pushd kolab
ci/testctl testrun
