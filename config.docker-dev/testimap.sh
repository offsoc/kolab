#!/bin/bash

set -e

trap 'echo "Error on $LINENO"' ERR

ADMIN_USER="cyrus-admin"
ADMIN_PASSWORD="Welcome2KolabSystems"
USER="admin@kolab.local"
PASSWORD="simple123"

USER_FRONTEND_COMMAND="imtest -p 7143 -a $USER -u $USER -w $PASSWORD -v 127.0.0.1"
USER_BACKEND_COMMAND="imtest -p 8143 -a $USER -u $USER -w $PASSWORD -v 127.0.0.1"
ADMIN_FRONTEND_COMMAND="imtest -p 7143 -a $ADMIN_USER -u $ADMIN_USER -w $ADMIN_PASSWORD -v 127.0.0.1"
ADMIN_BACKEND1_COMMAND="imtest -p 8143 -a $ADMIN_USER -u $ADMIN_USER -w $ADMIN_PASSWORD -v 127.0.0.1"
ADMIN_BACKEND2_COMMAND="imtest -p 9143 -a $ADMIN_USER -u $ADMIN_USER -w $ADMIN_PASSWORD -v 127.0.0.1"


# We expect to be able to create folders under the shared/ prefix from multiple backends.
# This only works because of a hardcoded exception, as folder hierarchies are not normally allowed to span backends.
echo 'a01 create shared/test1@kolab.local' | $ADMIN_BACKEND1_COMMAND
echo 'a01 create shared/test2@kolab.local' | $ADMIN_BACKEND1_COMMAND
echo 'a01 create shared/test3@kolab.local' | $ADMIN_BACKEND2_COMMAND
echo 'a01 create shared/test4@kolab.local' | $ADMIN_BACKEND2_COMMAND
echo 'a01 create shared/test5@kolab.local' | $ADMIN_FRONTEND_COMMAND

# Make sure no shared folder was created
docker compose exec -ti imap-mupdate ctl_mboxlist -d | grep -v '"shared":' > /dev/null 2>&1

# Make sure the folders exist
echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND | grep "shared/test1@kolab.local"
echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND | grep "shared/test2@kolab.local"
echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND | grep "shared/test3@kolab.local"
echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND | grep "shared/test4@kolab.local"
echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND | grep "shared/test5@kolab.local"


# Test getmetadata/setmetadata on a shared folder
echo 'a01 setmetadata shared/test1@kolab.local (/shared/vendor/kolab/folder-type "event")' | $ADMIN_FRONTEND_COMMAND | grep "a01 OK Completed"
echo 'a01 getmetadata (DEPTH infinity) shared/test1@kolab.local (/shared/vendor/kolab/folder-type)' | $ADMIN_FRONTEND_COMMAND | grep 'METADATA shared/test1@kolab.local ("/shared/vendor/kolab/folder-type" "event")'> /dev/null 2>&1
# Also test requesting multiple items
echo 'a01 getmetadata (DEPTH infinity) shared/test1@kolab.local (/shared/vendor/kolab/folder-type /private/vendor/kolab/folder-type)' | $ADMIN_FRONTEND_COMMAND | grep 'METADATA shared/test1@kolab.local ("/shared/vendor/kolab/folder-type" "event" "/private/vendor/kolab/folder-type" NIL)'> /dev/null 2>&1



# docker compose exec -ti webapp ./artisan scalpel:user:create --id=3 --email=john@kolab.local --password=simple123 --status=3
# $USER_FRONTEND_COMMAND
# We must login via IMAP first, to create the INBOX.
echo 'a01 list "" "*"' | $USER_BACKEND_COMMAND > /dev/null 2>&1
# Ensure the inbox is created
docker compose exec -ti imap-mupdate ctl_mboxlist -d | grep '"kolab.local!user.admin":' > /dev/null 2>&1

#TODO as  a user, all of those
# /home/mollekopf/bin/scripts/imapcli.rb delete --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER || true
# /home/mollekopf/bin/scripts/imapcli.rb create --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER
# /home/mollekopf/bin/scripts/imapcli.rb subscribe --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER
# /home/mollekopf/bin/scripts/imapcli.rb setmetadata --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER "/shared/vendor/kolab/folder-type" event
# /home/mollekopf/bin/scripts/imapcli.rb getmetadata --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER "/shared/*"
# /home/mollekopf/bin/scripts/imapcli.rb getmetadata --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER "/shared/vendor/kolab/folder-type"
# /home/mollekopf/bin/scripts/imapcli.rb lsub --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER
# /home/mollekopf/bin/scripts/imapcli.rb list --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER
# /home/mollekopf/bin/scripts/imapcli.rb delete --host $HOST --port 993 --username "$USERNAME" --password "$PASSWORD" --ssl --debug $FOLDER
