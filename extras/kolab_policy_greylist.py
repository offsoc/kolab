#!/usr/bin/python3
"""
An example implementation of a policy service.
"""

import json
import time
import sys

import requests


def read_request_input():
    """
        Read a single policy request from sys.stdin, and return a dictionary
        containing the request.
    """
    start_time = time.time()

    policy_request = {}
    end_of_request = False

    while not end_of_request:
        if (time.time() - start_time) >= 10:
            sys.exit(0)

        request_line = sys.stdin.readline()

        if request_line.strip() == '':
            if 'request' in policy_request:
                end_of_request = True
        else:
            request_line = request_line.strip()
            request_key = request_line.split('=')[0]
            request_value = '='.join(request_line.split('=')[1:])

            policy_request[request_key] = request_value

    return policy_request


if __name__ == "__main__":
    URL = 'https://services.kolabnow.com/api/webhooks/policy/greylist'

    # Start the work
    while True:
        REQUEST = read_request_input()

        try:
            RESPONSE = requests.post(
                URL,
                data=REQUEST,
                verify=True
            )
        # pylint: disable=broad-except
        except Exception:
            print("action=DEFER_IF_PERMIT Temporary error, try again later.")
            sys.exit(1)

        try:
            R = json.loads(RESPONSE.text)
        # pylint: disable=broad-except
        except Exception:
            sys.exit(1)

        if 'prepend' in R:
            for prepend in R['prepend']:
                print("action=PREPEND {0}".format(prepend))

        if RESPONSE.ok:
            print("action={0}\n".format(R['response']))

            sys.stdout.flush()
        else:
            print("action={0} {1}\n".format(R['response'], R['reason']))

            sys.stdout.flush()

        sys.exit(0)
