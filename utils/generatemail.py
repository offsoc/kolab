#!/bin/env python3
"""
    Generate a bunch of dummy messages in a folder.

    ./generate.py --clear --type=contact --count 1000 --username admin@kolab-vanilla.alma8.local --password W3lcom32@ph3lia --host localhost --port 9993 Contacts
"""
from datetime import datetime, timedelta
import pytz
import random
import argparse
import glob
import os
import imaplib


mailtemplate = '''
Return-Path: <christian@example.ch>
Received: from imapb010.mykolab.com ([unix socket])
         by imapb010.mykolab.com (Cyrus 2.5.10-49-g2e214b4-Kolab-2.5.10-8.1.el7.kolab_14) with LMTPA;
         Wed, 09 Aug 2017 18:37:01 +0200
X-Sieve: CMU Sieve 2.4
Received: from int-mx002.mykolab.com (unknown [10.9.13.2])
        by imapb010.mykolab.com (Postfix) with ESMTPS id 0A93910A25047
        for <christian@example.ch>; Wed,  9 Aug 2017 18:37:01 +0200 (CEST)
Received: from int-subm002.mykolab.com (unknown [10.9.37.2])
        by int-mx002.mykolab.com (Postfix) with ESMTPS id EC06AF6E
        for <christian@example.ch>; Wed,  9 Aug 2017 18:37:00 +0200 (CEST)
MIME-Version: 1.0
Content-Type: multipart/mixed;
 boundary="=_291b8e96564265636432c6d494e02322"
Date: {date}
From: "Mollekopf, Christian" <christian@example.ch>
To: christian@example.ch
Subject: {subject}
Message-ID: {messageid}

--=_291b8e96564265636432c6d494e02322
Content-Type: multipart/alternative;
 boundary="=_ceff0fd19756f45ed1295ee2069ff8e0"

--=_ceff0fd19756f45ed1295ee2069ff8e0
Content-Transfer-Encoding: 7bit
Content-Type: text/plain; charset=US-ASCII

sdlkjsdjf
--=_ceff0fd19756f45ed1295ee2069ff8e0
Content-Transfer-Encoding: quoted-printable
Content-Type: text/html; charset=UTF-8

<html><head><meta http-equiv=3D"Content-Type" content=3D"text/html; charset=
=3DUTF-8" /></head><body style=3D'font-size: 10pt; font-family: Verdana,Gen=
eva,sans-serif'>
<p>sdlkjsdjf</p>

</body></html>

--=_ceff0fd19756f45ed1295ee2069ff8e0--

--=_291b8e96564265636432c6d494e02322
Content-Transfer-Encoding: base64
Content-Type: text/plain;
 name=xorg.conf
Content-Disposition: attachment;
 filename=xorg.conf;
 size=211

U2VjdGlvbiAiRGV2aWNlIgogICAgSWRlbnRpZmllciAgICAgIkRldmljZTAiCiAgICBEcml2ZXIg
{attachment}ICAgIEJvYXJkTmFtZSAgICAgICJOVlMgNDIwME0iCiAgICBPcHRpb24gIk5vTG9nbyIgInRydWUi
CiAgICBPcHRpb24gIlVzZUVESUQiICJ0cnVlIgpFbmRTZWN0aW9uCg==
--=_291b8e96564265636432c6d494e02322--
'''.strip()

eventtemplate = '''
MIME-Version: 1.0
Content-Type: multipart/mixed;
 boundary="=_a56b92bc39b8cc011c0c98c3dcac708a"
From: admin@kolab-premium.centos7.local
To: admin@kolab-premium.centos7.local
Date: Thu, 07 May 2020 07:28:51 +0000
X-Kolab-Type: application/x-vnd.kolab.event
X-Kolab-Mime-Version: 3.0
Subject: {uid}
User-Agent: Kolab 16/Roundcube 1.4.2

--=_a56b92bc39b8cc011c0c98c3dcac708a
Content-Transfer-Encoding: quoted-printable
Content-Type: text/plain; charset=ISO-8859-1

This is a Kolab Groupware object. To view this object you will need an emai=
l client that understands the Kolab Groupware format. For a list of such em=
ail clients please visit http://www.kolab.org/


--=_a56b92bc39b8cc011c0c98c3dcac708a
Content-Transfer-Encoding: 8bit
Content-Type: application/calendar+xml; charset=UTF-8;
 name=kolab.xml
Content-Disposition: attachment;
 filename=kolab.xml;
 size=2724

<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<icalendar xmlns="urn:ietf:params:xml:ns:icalendar-2.0">
  <vcalendar>
    <properties>
      <prodid>
        <text>Roundcube-libkolab-1.1 Libkolabxml-1.2.0</text>
      </prodid>
      <version>
        <text>2.0</text>
      </version>
      <x-kolab-version>
        <text>3.1.0</text>
      </x-kolab-version>
    </properties>
    <components>
      <vevent>
        <properties>
          <uid>
            <text>{uid}</text>
          </uid>
          <created>
            <date-time>2020-05-07T07:28:51Z</date-time>
          </created>
          <dtstamp>
            <date-time>2020-05-07T07:28:51Z</date-time>
          </dtstamp>
          <sequence>
            <integer>0</integer>
          </sequence>
          <class>
            <text>PUBLIC</text>
          </class>
          <dtstart>
            <parameters>
              <tzid>
                <text>/kolab.org/Europe/Zurich</text>
              </tzid>
            </parameters>
            <date-time>{dtstart}</date-time>
          </dtstart>
          <dtend>
            <parameters>
              <tzid>
                <text>/kolab.org/Europe/Zurich</text>
              </tzid>
            </parameters>
            <date-time>{dtend}</date-time>
          </dtend>
          <summary>
            <text>{summary}</text>
          </summary>
          <description>
            <text>Testdescription</text>
          </description>
          <location>
            <text>Testlocation</text>
          </location>
          <organizer>
            <parameters/>
            <cal-address>mailto:%3Cadmin%40kolab-premium.centos7.local%3E</cal-address>
          </organizer>
          <attendee>
            <parameters>
              <partstat>
                <text>NEEDS-ACTION</text>
              </partstat>
              <role>
                <text>REQ-PARTICIPANT</text>
              </role>
              <rsvp>
                <boolean>true</boolean>
              </rsvp>
            </parameters>
            <cal-address>mailto:%3Ctest1%40kolab-premium.centos7.local%3E</cal-address>
          </attendee>
          <attach>
            <parameters>
              <fmttype>
                <text>text/x-ruby</text>
              </fmttype>
              <x-label>
                <text>safe_yaml</text>
              </x-label>
            </parameters>
            <uri>cid:safe_yaml.1588836531.13830</uri>
          </attach>
        </properties>
      </vevent>
    </components>
  </vcalendar>
</icalendar>

--=_a56b92bc39b8cc011c0c98c3dcac708a
Content-ID: <safe_yaml.1588836531.13830>
Content-Transfer-Encoding: base64
Content-Type: text/x-ruby;
 name=safe_yaml
Content-Disposition: attachment;
 filename=safe_yaml;
 size=540

IyEvdXNyL2Jpbi9ydWJ5CiMKIyBUaGlzIGZpbGUgd2FzIGdlbmVyYXRlZCBieSBSdWJ5R2Vtcy4K
{attachment}dAppZiBzdHIKICBzdHIgPSBzdHIuYlsvXEFfKC4qKV9cei8sIDFdCiAgaWYgc3RyIGFuZCBHZW06
ICJzYWZlX3lhbWwiLCB2ZXJzaW9uKQplbmQK
--=_a56b92bc39b8cc011c0c98c3dcac708a--
'''.strip()

contacttemplate = '''
MIME-Version: 1.0
Content-Type: multipart/mixed;
 boundary="=_a56b92bc39b8cc011c0c98c3dcac708a"
From: admin@kolab-premium.centos7.local
To: admin@kolab-premium.centos7.local
Date: Thu, 07 May 2020 07:28:51 +0000
X-Kolab-Type: application/x-vnd.kolab.contact
X-Kolab-Mime-Version: 3.0
Subject: {uid}
User-Agent: Kolab 16/Roundcube 1.4.2

--=_a56b92bc39b8cc011c0c98c3dcac708a
Content-Transfer-Encoding: quoted-printable
Content-Type: text/plain; charset=ISO-8859-1

This is a Kolab Groupware object. To view this object you will need an emai=
l client that understands the Kolab Groupware format. For a list of such em=
ail clients please visit http://www.kolab.org/


--=_a56b92bc39b8cc011c0c98c3dcac708a
Content-Transfer-Encoding: 8bit
Content-Type: application/vcard+xml; charset=UTF-8;
 name=kolab.xml
Content-Disposition: attachment;
 filename=kolab.xml;
 size=646

<?xml version="1.0" encoding="UTF-8" standalone="no" ?>
<vcards xmlns="urn:ietf:params:xml:ns:vcard-4.0">
  <vcard>
    <uid>
      <uri>urn:uuid:{uid}</uri>
    </uid>
    <x-kolab-version>
      <text>3.1.0</text>
    </x-kolab-version>
    <prodid>
      <text>Roundcube-libkolab-1.1 Libkolabxml-1.3.0</text>
    </prodid>
    <rev>
      <timestamp>20230428T134238Z</timestamp>
    </rev>
    <kind>
      <text>individual</text>
    </kind>
    <fn>
      <text>{given} {surname}</text>
    </fn>
    <email>
        <parameters>
            <type>
                <text>home</text>
            </type>
        </parameters>
        <text>{email}</text>
    </email>
  </vcard>
</vcards>

--=_a56b92bc39b8cc011c0c98c3dcac708a--
'''.strip()


class Generator:
    def __init__(self, options):
        self.target_directory = options.target_directory
        self.host = options.host
        self.port = options.port
        self.username = options.username
        self.password = options.password
        self.imap = imaplib.IMAP4_SSL(host=self.host, port=self.port)
        self.imap.login(options.username, options.password)

    def clearmailbox(self):
        #FIXME restore support for just writing to a directory
        # for f in glob.glob("{}/../cur/*".format(self.target_directory)):
        #     os.remove(f)
        self.imap.select(self.target_directory)
        typ, data = self.imap.search(None, 'ALL')
        for num in data[0].split():
            self.imap.store(num, '+FLAGS', '\\Deleted')
        self.imap.expunge()

    def upload(self, i, data):
        #FIXME restore support for just writing to a directory
        # fname = "{}/{}.".format(self.target_directory, i)
        # with open(fname, 'wb') as f:
        #     f.write(data.encode())

        ret = self.imap.append(self.target_directory, '', datetime.now(pytz.UTC), data.encode())
        if ret[0] != 'OK':
            raise Exception(ret)


    def populatemailbox(self, type, count):
        dtstamp = datetime.utcnow()

        # Reproducible results
        random.seed(30)

        for i in range(1, count + 1):
            dtstamp = dtstamp - timedelta(seconds=600)

            if type == "mail":
                attachmentMultiplier = 50000 * random.randint(0, 10)  # Approx 20 MB
                result = mailtemplate.format(
                    messageid="<foobar{}@example.org>".format(i),
                    subject="Foobar {}".format(i),
                    date=dtstamp.strftime("%a, %d %b %Y %H:%M:%S %z"),
                    attachment='ICAgIEJvYXJkTmFtZSAgICAgICJOVlMgNDIwME0iCiAgICBPcHRpb24gIk5vTG9nbyIgInRydWUi\n' * attachmentMultiplier
                )
            if type == "event":
                result = eventtemplate.format(
                    uid="B06DBE43D213419A9D705D6B7FAB2CA2-{}".format(i),
                    summary="Foobar {}".format(i),
                    dtstart=dtstamp.strftime("%Y-%m-%dT%H:%M:%S"),
                    dtend=(dtstamp + timedelta(seconds=300)).strftime("%Y-%m-%dT%H:%M:%S"),
                    attachment='dAppZiBzdHIKICBzdHIgPSBzdHIuYlsvXEFfKC4qKV9cei8sIDFdCiAgaWYgc3RyIGFuZCBHZW06\n' * 5000 * random.randint(0, 10)
                )
            if type == "contact":
                result = contacttemplate.format(
                    uid="B06DBE43D213419A9D705D6B7FAB2CA3-{}".format(i),
                    given="John {}".format(i),
                    surname="Doe {}".format(i),
                    email="doe{}@example.com".format(i),
                )
            print(i)
            self.upload(i, result)


parser = argparse.ArgumentParser(description='Generate some mail.')
parser.add_argument('target_directory', help='the target directory')
parser.add_argument('--count', help='Number of emails to generate', type=int)
parser.add_argument('--type', help='Type to generate', default='mail')
parser.add_argument('--clear', help='Type to generate', action='store_true')
parser.add_argument('--host', help='imap host', default='localhost')
parser.add_argument('--port', help='imap port', type=int, default=993)
parser.add_argument('--username', help='imap username')
parser.add_argument('--password', help='imap password')

args = parser.parse_args()

gen = Generator(args)
if args.clear:
    gen.clearmailbox()
gen.populatemailbox(args.type, args.count)
