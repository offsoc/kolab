#!/bin/bash

cp /usr/lib/python3.6/site-packages/pykolab/constants.py /tmp/constants.py
cp -Rf /src/pykolab/pykolab /usr/lib/python3.6/site-packages/
cp -Rf /src/pykolab/*.py /usr/lib/python3.6/site-packages/
cp /tmp/constants.py /usr/lib/python3.6/site-packages/pykolab/constants.py

systemctl restart wallace
systemctl restart kolabd
