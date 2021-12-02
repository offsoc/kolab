#!/bin/env python3
"""
    Generate a bunch of dummy messages in a folder.
"""
# import glob
# import os
from datetime import datetime, timedelta
import random
import argparse


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


def populatemailbox(target_directory, count):
    dtstamp = datetime.utcnow()

    # Reproducible results
    random.seed(30)

    for i in range(1, count + 1):
        dtstamp = dtstamp - timedelta(seconds=600)

        attachmentMultiplier = 50000 * random.randint(0, 10) # Approx 20 MB
        result = mailtemplate.format(
            messageid="<foobar{}@example.org>".format(i),
            subject="Foobar {}".format(i),
            date=dtstamp.strftime("%a, %d %b %Y %H:%M:%S %z"),
            attachment='ICAgIEJvYXJkTmFtZSAgICAgICJOVlMgNDIwME0iCiAgICBPcHRpb24gIk5vTG9nbyIgInRydWUi\n' * attachmentMultiplier
        )
        fname = "{}/{}.".format(target_directory, i)
        with open(fname, 'wb') as f:
            f.write(result.encode())


parser = argparse.ArgumentParser(description='Generate some mail.')
parser.add_argument('target_directory', help='the target directory')
parser.add_argument('--count', help='Number of emails to generate', type=int)

args = parser.parse_args()

populatemailbox(args.target_directory, args.count)
