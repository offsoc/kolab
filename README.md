## Quickstart Instructions to try it out

* Make sure you have docker and docker-compose available.
* Change to the base directory of this repository.
* Run './kolabctl deploy' to start the deployment.
* Add an /etc/hosts entry  "127.0.0.1 kolab.local"
* navigate to https://kolab.local
* login as "admin@kolab.local" with password "simple123" (or whatever you have set), and create your users.

# Customization

To customize the installation, copy config.prod and adjust to your liking. You can then deploy the configuration using 'env CONFIG=config.custom ./kolabctl deploy'.

Please note that './kolabctl deploy' will remove any existing data.

## Alternative configurations

Everything but config.prod is for development or demo purposes:
* config.prod: A docker environment with just an admin account prepared. A starting point for a production environment.
* config.demo: A docker environment with demo data included.
* config.docker-dev: A development environment with everything running in docker. Includes a cyrus-murder. Don't use unless you know what you're doing.
* config.host-dev: Run only dependencies in docker with ports exposed, and expect kolab4 to be run locally. Don't use unless you know what you're doing.
* config.legacy: A docker environment that includes ldap and other legacy components. Don't use unless you know what you're doing.


# Use the ansible setup

The ansible/ directory contains setup scripts to setup a fresh Fedora system with a kolab deployment.
Modify the Makefile with the required variables and then execute `make setup`.

This will configure the remote system and execute the above steps.

### Update

To update the containers without removing the data:

* git pull
* Run "./kolabctl update"

### Backup / Restore

The "./kolabctl backup" script will stop all containers, snapshot the volumes to the backup/ directory, and restart the containers.

"./kolabctl restore"  will stop all containers, restore the volumes from tarballs in the backup/ directory, and restart the containers.


### Requirements
* docker
* openssl
