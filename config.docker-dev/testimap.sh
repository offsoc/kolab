#!/bin/bash

set -e

trap 'echo "Error on $LINENO"' ERR

ADMIN_USER="cyrus-admin"
ADMIN_PASSWORD="Welcome2KolabSystems"
USER="admin@kolab.local"
PASSWORD="simple123"
CREDENTIALS="$USER:$PASSWORD"

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

# echo 'a01 list "" "*"' | imtest -p 7143 -a $USER -u $USER -w $PASSWORD -v 127.0.0.1
# echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND


# echo 'a01 list "" "*"' | $ADMIN_FRONTEND_COMMAND
# docker compose exec -ti imap-mupdate ctl_mboxlist -d | grep '"shared":'
# echo 'a01 setacl shared anyone +x' | $ADMIN_FRONTEND_COMMAND  > /dev/null 2>&1
# echo 'a01 delete shared' | $ADMIN_FRONTEND_COMMAND > /dev/null 2>&1
# echo 'a01 setacl shared/test4@kolab.local anyone +x' | $ADMIN_FRONTEND_COMMAND > /dev/null 2>&1
# echo 'a01 delete shared/test4@kolab.local' | $ADMIN_FRONTEND_COMMAND > /dev/null 2>&1
# # docker compose exec -ti imap-mupdate ctl_mboxlist -d

# # cyradm --user cyrus-admin --password $(awk '/mupdate_password/ {print $2}' /etc/imapd.conf)  --authz cyrus-admin localhost

# # curl -u $CREDENTIALS -i -X PROPFIND -H 'Depth: 1' $DAVSERVER/principals/user/$USER/

# # curl --user "$CREDENTIALS" -sD /dev/stderr -H "Content-Type: application/xml" -X PROPFIND -H "Depth: infinity" --data '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>' $DAVSERVER/principals/user/$USER/
# # | xmllint -format -

# # curl --user "$CREDENTIALS" -sD /dev/stderr -H "Content-Type: application/xml" -X PROPFIND -H "Depth: infinity" --data '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>' $DAVSERVER/calendars/user/$USER/ | xmllint -format -

