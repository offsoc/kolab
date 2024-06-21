#!/bin/bash

cp /etc/hosts /etc/hosts.orig
tac /etc/hosts.orig > /etc/hosts
