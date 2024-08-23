This is a container to run the migrate:userdata artisan command, which uses libpst to import .pst files.
This container builds on a regular kolab4 container for the artisan framework, but adds the migrate:userdata command which is only useful together with libpst (which is built in this container).
This container is not part of a regular kolab4 setup.


# Execution

A typical individual import could look like this:

podman run --network=host --rm -ti -v /home/mollekopf/src/kolab/docker/migrate/input:/opt/app-root/input/ -w /opt/app-root/src migrate ./artisan migrate:userdata --importpst="/opt/app-root/input/$INPUT" --username="$TARGETUSER" --password="$PASSWORD" --subscribe --debug --davUrl=https://apps.kolabnow.com --imapUrl=ssl://imap.kolabnow.com:993 --clear-target

# massmigrate.py 

This python script was used to massmigrate .zip files with .pst data.
It will require adjustment to be used, and should probably be executed inside the container.


