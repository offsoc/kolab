#!/usr/bin/python3
# Test like so: cat test.mime | /usr/libexec/postfix/kolab_contentfilter_cli.py -f admin@kolab.local -- noreply@kolab.local
# from email import Parser
# import smtplib
import sys
import logging
from subprocess import Popen, PIPE

SUCCESS = 0
# Postfix will bounce and not retry
BOUNCE = 69
# Postfix will retry
TEMPFAIL = 75
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
        sys.exit(BOUNCE)


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
            return SUCCESS
        else:
            raise Exception("retval not zero - %s" % retval)
    except Exception as e:
        print(f"Error re-injecting via {command}")
        if verbose:
            logging.error("Exception while re-injecting mail: %s -- stdout:%s, stderr:%s, retval: %s" % (e, stdout, stderr, retval))
            logging.error(f"Commandline used: {command}")
        return TEMPFAIL


(cli_from, cli_to) = get_args()

if verbose:
    logging.basicConfig(level=logging.DEBUG,
                        format='%(asctime)s %(levelname)s %(message)s',
                        filename='/tmp/content-filter.log',
                        filemode='a')


# logging.debug("From : %s, to : %r" % (cli_from, cli_to))

content = ''.join(sys.stdin.readlines())
#TODO send content to kolab4

sys.exit(reinject(content, cli_from, cli_to))

