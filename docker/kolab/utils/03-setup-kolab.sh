#!/bin/bash

setup-kolab \
    --default \
    --fqdn=kolab.mgmt.com \
    --timezone=Europe/Zurich \
    --mysqlhost=127.0.0.1 \
    --mysqlserver=existing \
    --mysqlrootpw=Welcome2KolabSystems \
    --directory-manager-pwd=Welcome2KolabSystems 2>&1 | tee /root/setup-kolab.log

