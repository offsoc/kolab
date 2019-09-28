#!/bin/bash

pushd /root/utils/

while [ ! -f /tmp/kolab-init.done ]; do
    sleep 5
done

./50-add-vlv-searches.sh
./51-add-vlv-indexes.sh
./52-run-vlv-index-tasks.sh
