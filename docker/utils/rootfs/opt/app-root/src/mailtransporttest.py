#!/bin/env python3
"""
    Send an email via SMTP and then look for it via IMAP.

    ./mailtransporttest.py --sender-username test1@kolab.org --sender-password foobar --sender-host smtp.kolabnow.com --recipient-username test2@kolab.org --recipient-password foobar --recipient-host imap.kolabnow.com
"""
from datetime import datetime, UTC
import argparse
import sys
import imaplib
import smtplib
import uuid
import time


mailtemplate = '''
MIME-Version: 1.0
Content-Type: multipart/mixed;
 boundary="=_291b8e96564265636432c6d494e02322"
Date: {date}
From: {sender}
To: {to}
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



class SendTest:
    def __init__(self, options):
        self.recipient_host = options.recipient_host
        self.recipient_username = options.recipient_username
        self.recipient_password = options.recipient_password

        self.sender_host = options.sender_host
        self.sender_username = options.sender_username
        self.sender_password = options.sender_password

        self.uuid = str(uuid.uuid4())
        self.subject = f"Delivery Check {self.uuid}"

    def check_for_mail(self):
        print(f"Checking for uuid {self.uuid}")
        imap = imaplib.IMAP4_SSL(host=self.recipient_host, port=993)
        imap.login(self.recipient_username, self.recipient_password)
        imap.select("INBOX")
        typ, data = imap.search(None, 'SUBJECT', '"' + self.subject + '"')
        for num in data[0].split():
            print(f"Found the mail with uid {num}")
            imap.store(num, '+FLAGS', '\\Deleted')
            imap.expunge()
            return True
        return False

    def send_mail(self, starttls):
        dtstamp = datetime.now(UTC)
        msg = mailtemplate.format(
            messageid="<{}@deliverycheck.org>".format(self.uuid),
            subject=self.subject,
            sender=self.sender_username,
            to=self.recipient_username,
            date=dtstamp.strftime("%a, %d %b %Y %H:%M:%S %z"),
            attachment='ICAgIEJvYXJkTmFtZSAgICAgICJOVlMgNDIwME0iCiAgICBPcHRpb24gIk5vTG9nbyIgInRydWUi\n'
        )
        if starttls:
            with smtplib.SMTP(host=self.sender_host, port=587) as smtp:
                smtp.starttls()
                smtp.ehlo()
                smtp.login(self.sender_username, self.sender_password)
                smtp.noop()
                smtp.sendmail(self.sender_username, self.recipient_username, msg)
                print(f"Email with uuid {self.uuid} sent")
        else:
            with smtplib.SMTP_SSL(host=self.sender_host, port=465) as smtp:
                smtp.login(self.sender_username, self.sender_password)
                smtp.noop()
                smtp.sendmail(self.sender_username, self.recipient_username, msg)
                print(f"Email with uuid {self.uuid} sent")


parser = argparse.ArgumentParser(description='Mail transport tests.')
parser.add_argument('--sender-username', help='The SMTP sender username')
parser.add_argument('--sender-password', help='The SMTP sender password')
parser.add_argument('--sender-host', help='The SMTP sender host')
parser.add_argument('--recipient-username', help='The IMAP recipient username')
parser.add_argument('--recipient-password', help='The IMAP recipient password')
parser.add_argument('--recipient-host', help='The IMAP recipient host')
parser.add_argument('--timeout', help='Timeout in minutes', type=int, default=10)
parser.add_argument("--starttls", action='store_true', help="Use starttls over 587")

args = parser.parse_args()

obj = SendTest(args)
obj.send_mail(args.starttls)

timeout = 10

for i in range(1, round(args.timeout * 60 / timeout) + 1):
    if obj.check_for_mail():
        print("Success!")
        sys.exit(0)
    print(f"waiting for {timeout}")
    time.sleep(timeout)


print("Failed to find the mail")
sys.exit(1)
