#!/bin/bash

mysql -e 'drop database kolab;'
mysql -e 'create database kolab;'

./artisan migrate:refresh --seed
