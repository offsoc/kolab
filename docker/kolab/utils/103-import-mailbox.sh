#!/bin/bash

# Run like so: utils/103-import-mailbox.sh /root/data/maildata/

echo "sam user/john/* cyrus-admin c;
dm user/john/Test@kolab.org;
cm user/john/Test@kolab.org;
sam user/john cyrus c;
" | cyradm --auth PLAIN -u cyrus-admin -w Welcome2KolabSystems --port 11143 127.0.0.1

echo "sam user/john/* cyrus c;
subscribe Test;
" | cyradm --auth PLAIN -u john@kolab.org -w simple123 --port 11143 127.0.0.1

FOLDERPATH=/var/spool/imap/domain/k/kolab.org/j/user/john/Test
mkdir -p $FOLDERPATH

cp "$1"/* $FOLDERPATH/

chown -R cyrus:mail $FOLDERPATH
/usr/lib/cyrus-imapd/reconstruct "user/john/Test@kolab.org"
