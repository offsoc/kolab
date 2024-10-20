#!/bin/env python3
"""
    Send an email via SMTP and then look for it via IMAP.

    ./mailtransporttest.py --sender-username test1@kolab.org --sender-password foobar --sender-host smtp.kolabnow.com --recipient-username test2@kolab.org --recipient-password foobar --recipient-host imap.kolabnow.com
"""
from datetime import datetime
import argparse
import sys
import imaplib
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
        self.recipient_host = options.recipient_host
        self.recipient_port = options.recipient_port
        self.recipient_username = options.recipient_username
        self.recipient_password = options.recipient_password

        self.sender_host = options.sender_host
        self.sender_port = options.sender_port
        self.sender_username = options.sender_username
        self.sender_password = options.sender_password

        self.target_address = options.target_address

        self.body = options.body
        self.verbose = options.verbose

        self.uuid = str(uuid.uuid4())
        self.subject = f"Delivery Check {self.uuid}"

    def check_for_mail(self):
        print(f"Checking for uuid {self.uuid}")
        imap = imaplib.IMAP4_SSL(host=self.recipient_host, port=self.recipient_port)
        if self.verbose:
            imap.debug = 4
        imap.login(self.recipient_username, self.recipient_password)
        imap.select("INBOX")
        # FIXME This seems to find emails that are not there
        if self.body:
            typ, data = imap.search(None, 'BODY', self.uuid)
        else:
            typ, data = imap.search(None, 'SUBJECT', self.uuid)
        for num in data[0].split():
            print(f"Found the mail with uid {num}")
            imap.store(num, '+FLAGS', '\\Deleted')
            imap.expunge()
            return True
        return False

    def send_mail(self, starttls):
        dtstamp = datetime.utcnow()

        if self.target_address:
            to = self.target_address
        else:
            to = self.recipient_username

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
parser.add_argument('--sender-port', help='The SMTP sender port', default=993)
parser.add_argument('--recipient-username', help='The IMAP recipient username')
parser.add_argument('--recipient-password', help='The IMAP recipient password')
parser.add_argument('--recipient-host', help='The IMAP recipient host')
parser.add_argument('--recipient-port', help='The IMAP recipient port (defaults to 465/587)')
parser.add_argument('--timeout', help='Timeout in minutes', type=int, default=10)
parser.add_argument("--starttls", action='store_true', help="Use starttls over 587")
parser.add_argument("--verbose", action='store_true', help="Use starttls over 587")
parser.add_argument("--target-address", help="Target address instead of the recipient username")
parser.add_argument("--body", help="Body text to include")

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
