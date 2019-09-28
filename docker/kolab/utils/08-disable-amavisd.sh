#!/bin/bash

postconf -e content_filter='smtp-wallace:[127.0.0.1]:10026'

systemctl restart postfix

systemctl stop amavisd
systemctl disable amavisd

systemctl stop clamd@amavisd
systemctl disable clamd@amavisd
