#!/bin/bash

set -e

trap 'echo "Error on $LINENO"' ERR

USER="admin@kolab.local"
PASSWORD="simple123"
CREDENTIALS="$USER:$PASSWORD"
DAVSERVER="http://localhost:7081/dav"

USER_FRONTEND_COMMAND="imtest -p 7143 -a $USER -u $USER -w $PASSWORD -v 127.0.0.1"
USER_BACKEND_COMMAND="imtest -p 8143 -a $USER -u $USER -w $PASSWORD -v 127.0.0.1"

# We just connect to a backend to make sure the inbox is created
echo 'a01 list "" "*"' | $USER_BACKEND_COMMAND > /dev/null 2>&1


# This is supposed to create the calendar
curl -u $CREDENTIALS -i -X PROPFIND -H 'Depth: 1' "$DAVSERVER/principals/user/$USER/" | grep "HTTP/1.1 207 Multi-Status"

curl -u $CREDENTIALS -sD /dev/stderr -H "Content-Type: application/xml" -X PROPFIND -H "Depth: infinity" --data '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>' "$DAVSERVER/calendars/user/$USER" | grep "<d:status>HTTP/1.1 200 OK</d:status>"

# Test a PUT
curl --user "$CREDENTIALS" -X PUT $DAVSERVER/calendars/user/$USER/Default/VYM30SH4VNJFV4CWAUC0V10.ics -H "Content-Type: text/calendar ; charset=utf-8" -T /dev/stdin <<-EOF
BEGIN:VCALENDAR
PRODID:-//My own caldav script
VERSION:2.0
CALSCALE:GREGORIAN
BEGIN:VEVENT
CREATED:20190606T094245
DTSTAMP:20190606T094245
LAST-MODIFIED:20190606T094245
UID:VYM30SH4VNJFV4CWAUC0V92310
SUMMARY:Test added using cURL !
CLASS:PUBLIC
STATUS:CONFIRMED
DTSTART;TZID=Europe/Zurich:20190606T200000
DTEND;TZID=Europe/Zurich:20190606T210000
END:VEVENT
BEGIN:VTIMEZONE
TZID:Europe/Zurich
BEGIN:DAYLIGHT
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
TZNAME:CEST
DTSTART:19700329T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
TZNAME:CET
DTSTART:19701025T030000
RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU
END:STANDARD
END:VTIMEZONE
END:VCALENDAR
EOF

# List the event
curl --user "$CREDENTIALS" -sD /dev/stderr -H "Content-Type: application/xml" -X PROPFIND -H "Depth: infinity" $DAVSERVER/calendars/user/$USER/Default/ | xmllint -format - | grep "<D:href>/dav/calendars/user/admin@kolab.local/Default/VYM30SH4VNJFV4CWAUC0V10.ics</D:href>"
