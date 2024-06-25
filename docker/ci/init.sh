#!/bin/bash
set -e
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
