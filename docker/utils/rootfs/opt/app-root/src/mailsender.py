#!/bin/env python3
"""
    Send an email via SMTP

    ./mailsender.py --sender-username test1@kolab.org --sender-password foobar --sender-host smtp.kolabnow.com --target-address test2@kolab.org
"""
from datetime import datetime
import argparse
import sys
import smtplib
import uuid
import time


mailtemplate = '''
MIME-Version: 1.0
Date: {date}
From: {sender}
To: {to}
Subject: {subject}
Message-ID: {messageid}
Content-Transfer-Encoding: 7bit
Content-Type: text/plain; charset=US-ASCII

{body}
'''.strip()


class SendTest:
    def __init__(self, options):
        self.sender_host = options.sender_host
        self.sender_port = options.sender_port
        self.sender_username = options.sender_username
        self.sender_password = options.sender_password

        self.target_address = options.target_address

        self.body = options.body
        self.verbose = options.verbose

        self.uuid = str(uuid.uuid4())
        self.subject = f"Delivery Check {self.uuid}"

    def send_mail(self, starttls):
        dtstamp = datetime.utcnow()

        to = self.target_address

        print(f"Sending email to {to}")

        msg = mailtemplate.format(
            messageid="<{}@deliverycheck.org>".format(self.uuid),
            subject=self.subject,
            sender=self.sender_username,
            to=to,
            date=dtstamp.strftime("%a, %d %b %Y %H:%M:%S %z"),
            body=self.body,
        )
        if starttls:
            with smtplib.SMTP(host=self.sender_host, port=self.sender_port or 587) as smtp:
                smtp.starttls()
                smtp.ehlo()
                smtp.login(self.sender_username, self.sender_password)
                smtp.noop()
                smtp.sendmail(self.sender_username, to, msg)
                print(f"Email with uuid {self.uuid} sent")
        else:
            with smtplib.SMTP_SSL(host=self.sender_host, port=self.sender_port or 465) as smtp:
                smtp.login(self.sender_username, self.sender_password)
                smtp.noop()
                smtp.sendmail(self.sender_username, to, msg)
                print(f"Email with uuid {self.uuid} sent")


parser = argparse.ArgumentParser(description='Mail transport tests.')
parser.add_argument('--sender-username', help='The SMTP sender username')
parser.add_argument('--sender-password', help='The SMTP sender password')
parser.add_argument('--sender-host', help='The SMTP sender host')
parser.add_argument('--sender-port', help='The SMTP sender port (defaults to 465/587)')
parser.add_argument('--timeout', help='Timeout in minutes', type=int, default=10)
parser.add_argument("--starttls", action='store_true', help="Use SMTP starttls over 587")
parser.add_argument("--verbose", action='store_true', help="Verbose mode")
parser.add_argument("--target-address", help="Target address")
parser.add_argument("--body", help="Body text to include")
parser.add_argument("--count", help="Number of messages to send", type=int, default=1)

args = parser.parse_args()

timeout = 0
for i in range(1, args.count + 1):
    obj = SendTest(args)
    obj.send_mail(args.starttls)
    print(f"waiting for {timeout}")
    time.sleep(timeout)

sys.exit(0)
