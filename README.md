# Quickstart Instructions to try it out

* Make sure you have podman available.
* Change to the base directory of this repository.
* Run './kolabctl configure' to generate the .env file.
* Run './kolabctl deploy' to start the deployment.
* Add an /etc/hosts entry  "127.0.0.1 kolab.local"
* Navigate to https://kolab.local
* Login as "admin@kolab.local" with the password set during the deploy step, and create your users.

# Podman deployment

The podman deployment can be managed using the kolabctl script, which is a wrapper around various podman commands.

The kolabctl script can be used to:
* Build all images: './kolabctl build'
* Create a pod running all containers: './kolabctl deploy'
* Execute various maintenance tasks:
** Start/stop the pod
** Backup/restore the volumes
** Validate the deployment via './kolabctl selfcheck'

## Host Requirements
* podman
* openssl

## Update

To update the containers without removing the data:

* git pull
* Run "./kolabctl update"

## Backup / Restore

The "./kolabctl backup" script will stop all containers, snapshot the volumes to the backup/ directory, and restart the containers.

"./kolabctl restore"  will stop all containers, restore the volumes from tarballs in the backup/ directory, and restart the containers.


# Customization

To customize the installation, copy config.prod and adjust to your liking.

To generate a new .env based on that configuration 'env CONFIG=config.custom ./kolabctl configure --force'.
You can then deploy the configuration using 'env CONFIG=config.custom ./kolabctl deploy --reset'.

Please note that './kolabctl deploy --reset' will remove any existing data.

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

# Use the kubernetes deployment

A helm chart and prebuilt images for a kubernetes deployment are available here: https://mirror.apheleia-it.ch/pub/kolab-kubernetes-latest.tar.gz

The tarball contains instructions as well as a kolabctl script to setup a k3s based deployment.

Please note:
* The prebuilt images are based on the same sources built by this repository.
* The images and helm chart come without support or guarantees for backwards comaptiblity.
* If you want to run this in production you need to either know what you're doing or get support.

