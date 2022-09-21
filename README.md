## Quickstart Instructions to try it out

* Make sure you have docker and docker-compose available.
* Run 'make deploy' in the base directory.
* Add an /etc/hosts entry  "127.0.0.1 kolab.local"
* navigate to https://kolab.local
* login as "john@kolab.org" with password "simple123"

# Setup env.local

To customize the installation, create a file src/env.local to override setting in src/.env.example.

The setup script with merge these settings into src/.env, which is what is ultimately used by the installation.

Take a look at ansible/env.local for an example of typical modifications required for an installation.

# Use the ansible setup

The ansible/ directory contains setup scripts to setup a fresh Fedora system with a kolab deployment.
Modify the Makefile with the required variables and then execute `make setup`.

This will configure the remote system and execute bin/deploy.sh

### Update

* git pull
* Run "bin/update.sh"

### Backup / Restore

The "bin/backup.sh" script will stop all containers, snapshot the volumes to the backup/ directory, and restart the containers.

"bin/restore.sh"  will stop all containers, restore the volumes from tarballs in the backup/ directory, and restart the containers.


### Requirements
* docker
* openssl

## TODO
* Only seed admin user, but not all the development stuff?
