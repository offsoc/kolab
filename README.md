## Quickstart Instructions to try it out

* Make sure you have docker and docker-compose available.
* Change to the base directory of this repository.
* Run 'HOSTNAME=kolab.local ADMIN_PASSWORD="simple123" bin/configure.sh config.prod' to configure this deployment.
* Run 'bin/deploy.sh' to start the deployment.
* Run 'docker exec -w /src/kolabsrc/ kolab-webapp ./artisan user:password admin@kolab.local simple123' to set your admin password
* Add an /etc/hosts entry  "127.0.0.1 kolab.local"
* navigate to https://kolab.local
* login as "admin@kolab.local" with password "simple123" (or whatever you have set), and create your users.

# Customization

To customize the installation, copy config.prod and adjust to your liking. You can then install the configuration using 'bin/configure.sh $YOURCONFIG',
and afterwards 'bin/deploy.sh' again.

Please note that bin/deploy.sh will remove any existing data.

# Use the ansible setup

The ansible/ directory contains setup scripts to setup a fresh Fedora system with a kolab deployment.
Modify the Makefile with the required variables and then execute `make setup`.

This will configure the remote system and execute the above steps.

### Update

To update the containers without removing the data:

* git pull
* Run "bin/update.sh"

### Backup / Restore

The "bin/backup.sh" script will stop all containers, snapshot the volumes to the backup/ directory, and restart the containers.

"bin/restore.sh"  will stop all containers, restore the volumes from tarballs in the backup/ directory, and restart the containers.


### Requirements
* docker
* openssl
