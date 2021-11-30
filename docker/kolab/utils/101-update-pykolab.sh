#!/bin/bash

cp /usr/lib/python2.7/site-packages/pykolab/constants.py /tmp/constants.py
cp -Rf /src/pykolab/pykolab /usr/lib/python2.7/site-packages/
cp /tmp/constants.py /usr/lib/python2.7/site-packages/pykolab/constants.py

systemctl restart wallace
systemctl restart kolabd
