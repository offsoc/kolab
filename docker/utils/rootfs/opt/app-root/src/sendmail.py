#!/bin/env python3
"""
    Utilty to send an email.

    Primarily useful to quickly generate test messages to e.g. troubleshoot mail delivery.

    ./sendmail.py --sender-username admin@kolab.local --sender-password test123 --to test@kolab.org --subject 'test4' --body 'test4'"""
from datetime import datetime
import argparse
import sys
import smtplib
import uuid


mailtemplate = '''
MIME-Version: 1.0
Content-Type: text/plain;
Date: {date}
From: {sender}
To: {to}
Subject: {subject}
Message-ID: {messageid}

{body}
'''.strip()



class SendMail:
    def __init__(self, options):
        self.sender_username = options.sender_username
        self.sender_password = options.sender_password
        self.to = options.to

        self.uuid = str(uuid.uuid4())
        self.subject = options.subject
        self.body = options.body

    def send_mail(self):
        dtstamp = datetime.utcnow()
        msg = mailtemplate.format(
            messageid="<{}@sendmail.py>".format(self.uuid),
            subject=self.subject,
            body=self.body,
            sender=self.sender_username,
            to=self.to,
            date=dtstamp.strftime("%a, %d %b %Y %H:%M:%S %z"),
        )
        with smtplib.SMTP(host="postfix", port=10587) as smtp:
            smtp.starttls()
            smtp.ehlo()
            smtp.login(self.sender_username, self.sender_password)
            smtp.noop()
            smtp.sendmail(self.sender_username, self.to, msg)
            print(f"Email with uuid {self.uuid} sent")


parser = argparse.ArgumentParser(description='Mail transport tests.')
parser.add_argument('--sender-username', help='The SMTP sender username')
parser.add_argument('--sender-password', help='The SMTP sender password')
parser.add_argument('--to', help='The IMAP recipient username')
parser.add_argument('--subject', help='Subject')
parser.add_argument('--body', help='Body')

args = parser.parse_args()

obj = SendMail(args)
obj.send_mail()

sys.exit(0)
