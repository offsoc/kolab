#!/bin/bash
# Inject a mime message a users inbox.
# * Write a mime message to $file
# * Run 'injectmimemessage.sh $file' to inject the message into the TO users mailbox.
# * Run 'injectmimemessage.sh $file $user@example.com' to inject the message into the $user@example.com's mailbox.

if [ -z "$2" ]
then
    TO="-t" #use "To:" header instead
else
    TO=$2
fi

# The message id must be unique, otherwise messages get deduplicated.
RANDOMID=$(tr -dc A-Za-z0-9 </dev/urandom | head -c 13 ; echo '')
sed "s/message-id:.*/Message-Id: <$RANDOMID@generated.local>/gI" "$1" | /usr/sbin/sendmail -i "$TO"
