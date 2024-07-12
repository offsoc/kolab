# Kolab: Open Source Email and Groupware

Kolab is a completely open source email and groupware platform.

Among it's features it provides:
* Email/Calendar/Addressbook/Tasks/Files
* IMAP/SMTP/CalDAV/CardDAV/ActiveSync
* Voice & Video via WebRTC

For more information, refer to kolab.org

# Deployment

Kolab is prepared as a fully containerized solution. While the OCI compatible containers can be run anywhere in principle, they are normally run on either podman for single-host development/test instances or k3s/Openshift for production deployments.

## What about Packages?

The latest generation of Kolab is no longer being packaged. While packages for various Kolab 3 components continue to be maintained, this is no longer recommended for complete Kolab installations.

## Quickstart Instructions to try it out

To just get the containers running for demo or test purposes follow these instructions.

* Make sure you have podman available.
* Change to the base directory of this repository.
* Run './kolabctl configure' to generate the .env file.
* Run './kolabctl deploy' to start the deployment.
* Add an /etc/hosts entry  "127.0.0.1 kolab.local"
* Navigate to https://kolab.local
* Login as "admin@kolab.local" with the password set during the deploy step, and create your users.

## Podman deployment

The podman deployment can be managed using the kolabctl script, which is a wrapper around various podman commands.

The kolabctl script can be used to:
* Build all images: './kolabctl build'
* Create a pod running all containers: './kolabctl deploy'
* Execute various maintenance tasks:
** Start/stop the pod
** Backup/restore the volumes
** Validate the deployment via './kolabctl selfcheck'

### Host Requirements
* podman
* openssl
* Approx. 10GB of disk space for image storage

### Who is this for?

The podman deployment is suitable as a basis for a private use deployment.
It is likely that adjustments will be required to match your exact needs.

Please note that to run this in production expertise is required.
If you do not understand the individual underlying components it is not recommended to run a production deployment.

### Deployment

To deploy kolab on a host with a public IP and domain name the following steps are required:

* Run 'HOST=kolab.example.com PUBLIC_IP=yourpublicip ADMIN_PASSWORD=youradminpassword ./kolabctl configure'
* Run 'ADMIN_PASSWORD=youradminpassword ./kolabctl deploy'
* Validate the installation via './kolabctl selfcheck'

### Update

To update the containers without removing the data:

* git pull
* Run "./kolabctl update"

### Backup / Restore

The "./kolabctl backup" script will stop all containers, snapshot the volumes to the backup/ directory, and restart the containers.

"./kolabctl restore"  will stop all containers, restore the volumes from tarballs in the backup/ directory, and restart the containers.

### Reset

"./kolabctl reset" will delete all data volumes to restart a deployment from scratch.

To reconfigure as well use the '--force' option to './kolabctl configure'

### Customization

To customize the installation, copy config.prod and adjust to your liking.

To deploy the new configuration, pass the 'CONFIG=config.custom' environment variable to the configure and deploy steps above.

### Alternative configurations

Everything but config.prod is for development or demo purposes.

## Use the ansible setup

The ansible/ directory contains setup scripts to setup a fresh Fedora system with a podman based kolab deployment.
Modify the Makefile with the required variables and then execute `make setup`.

This will configure the remote system and execute the above steps.

## Use the kubernetes deployment

A helm chart and prebuilt images for a kubernetes deployment are available here: https://mirror.apheleia-it.ch/pub/kolab-kubernetes-latest.tar.gz

The tarball contains instructions as well as a kolabctl script to setup a k3s based deployment.

Please note:
* The prebuilt images are based on the same sources built by this repository.
* The images and helm chart come without support or guarantees for backwards comaptiblity.
* If you want to run this in production you need to either know what you're doing or buy support. Patches are of course welcome.

