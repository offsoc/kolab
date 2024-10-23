#!/usr/bin/python3
# Test like so: cat test.mime | /usr/libexec/postfix/kolab_contentfilter_cli.py -f admin@kolab.local -- noreply@kolab.local
import sys
import logging
import requests
from subprocess import Popen, PIPE

EX_SUCCESS = 0
# Postfix will bounce and not retry
EX_UNAVAILABLE = 69
# Postfix will retry via deferred queue
EX_TEMPFAIL = 75
verbose = True


def get_args():
    try:
        if verbose:
            logging.debug("ARGV : %r" % sys.argv)
        cli_from = sys.argv[2].lower()
        cli_to = sys.argv[4:]
        return (cli_from, cli_to)
    except Exception:
        if verbose:
            logging.error("Invalid to / from : %r" % sys.argv)
        sys.exit(EX_UNAVAILABLE)


def reinject(content, sender, to):
    command = ["/usr/sbin/sendmail", "-G", "-i", "-f", sender] + to
    stdout = ''
    stderr = ''
    retval = 0
    try:
        process = Popen(command, stdin=PIPE)
        (stdout, stderr) = process.communicate(content.encode())
        retval = process.wait()
        if retval == 0:
            if verbose:
                logging.debug("Mail resent via sendmail, stdout: %s, stderr: %s" % (stdout, stderr))
            return EX_SUCCESS
        else:
            raise Exception("retval not zero - %s" % retval)
    except Exception as e:
        print(f"Error re-injecting via {command}")
        if verbose:
            logging.error("Exception while re-injecting mail: %s -- stdout:%s, stderr:%s, retval: %s" % (e, stdout, stderr, retval))
            logging.error(f"Commandline used: {command}")
        return EX_TEMPFAIL


if __name__ == "__main__":
    (cli_from, cli_to) = get_args()

    if verbose:
        logging.basicConfig(level=logging.DEBUG,
                            format='%(asctime)s %(levelname)s %(message)s',
                            filename='/tmp/content-filter.log',
                            filemode='a')

    content = ''.join(sys.stdin.readlines())

    URL = 'SERVICES_HOST/api/webhooks/policy/mail/filter'
    try:
        response = requests.post(
            URL + f"?recipient={cli_to}",
            data=content,
            verify=True
        )

        if response.status_code == 201:
            if verbose:
                logging.warning("No changes requested, reinjecting original content.")
            print("Unmodified email")
        elif response.status_code == 200:
            if verbose:
                logging.warning("Updating content.")
            print("Modified email")
            content = response.text
        elif response.status_code == 460:
            if verbose:
                logging.warning("Rejecting email.")
            print("Rejecting email")
            sys.exit(EX_UNAVAILABLE)
        elif response.status_code == 461:
            if verbose:
                logging.warning("Ignoring email.")
            print("Dropping email")
            sys.exit(EX_SUCCESS)
        else:
            if verbose:
                logging.warning(f"Unknown status code {response.status_code}.")
            print("Unknown status code")
            sys.exit(EX_TEMPFAIL)

    # pylint: disable=broad-except
    except Exception:
        if verbose:
            logging.warning("Request failed.")
        print("Request failed")
        sys.exit(EX_TEMPFAIL)

    sys.exit(reinject(content, cli_from, cli_to))
