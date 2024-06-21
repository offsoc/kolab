# Setup a new node with a kolab deployment.

* Make sure you're running on cgroupv1 for docker to work (needs a kernel option and reboot if not already done, see bin/quickstart.sh).
* Set secrets in makefile
* Set host in hosts file
* Run "make setup" to execute ansible-playbook to install packages and fetch things
* ssh into system:
** sudo certbot certonly --standalone -d $hostname
** sudo chmod 755 -R /etc/letsencrypt/
** run bin/quickstart.sh
** ./artisan user:assign john@kolab.org meet
** ./artisan user:assign john@kolab.org beta

