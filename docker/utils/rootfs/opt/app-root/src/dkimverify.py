#!/usr/bin/env python

from __future__ import print_function

import sys
import argparse

import dkim

def dns_get_txt(name, timeout=5):
    # Do the actual dns lookup like so
    # from dkim.dnsplug import get_txt
    # result = get_txt(name, timeout)
    # print(result)
    # return result
    # Or just hardocde a result like so
    # return b'v=DKIM1; k=rsa; p=MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCyqAhkiHzqM5twQRO3BysIb7yyZZjAqZG2ufEIWcpK+fXo//zS9tMJz1Ic4A1Df+EESxBWZ068uBMORZ2CQSiuZKvOCsin35hNKc7V1nhz3GKOC0OxqCXOVqZIpUlCPBYz5iKi/8sXu+yhAbESpbPOYecgRKBykGmnZnyfN7y+0QIDAQAB'

    #kolab.local
    # return b'v=DKIM1; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAx2xsAfAMWUjhbTSdNIc1hehRsF4n3P+V25x/z67cAO72EN8m7hgXvsTXaxd+fW8+zHCB7JMuLppRdBYBDS/1iDM9QKvp6coGedViVBqDJjEiEoHRRBuSNQOlGl/QNYxpEjCfuYmz87Jrz0iCKUFKgTre1sQvyToFhcW1R0i6Jrcsgm8Yz1cyMBaoZ5P+kRZ58MZsEnSGIxrj9M8upKTD9IZe60Tv8pixac1vtzV9/+RsZ0PI6li5n5nrorGACiy2jiBhuI4Gznm+AjYurPSVGnZOexQEK8mC/EEJ9aFLxlczD7m7t2rL+JUoP3WWLhTHzxtxtj1QmSkveZN4VeAKLwIDAQAB'
  
    #kolab.klab.cc
    return b'v=DKIM1; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA7JdTEn/T2MhB6KLbATJj4SGernbem4d7dAW7/kVRbiMB2EtPpQCR98eeXOOHcufXVc3w4BocXEnD47JPpkFYJBXWF32m4Y2SapBYbsXndN9fyXRrHO9wPJlW5QK5i9D/bRUznfaBJm54y+BuX0Ln/ippqFe6z3LPjmro9Y9WpRzevYG/TT69Iug5v4U/PA1/rEv+zZGQvNxInZYF7O2MFDbD2pYi7l4hWADP+iwOEc+Li5vPlEvOaUlSQCb06sc0/QBHDDyU2WaEJiYy/Mk2xCmSI44f3mQghmiNsu7vlPiztAYKjNyiVk8iWDttL9OV9qQyxHL18Q74UzJR6AylrwIDAQAB'

    #old kolab.klab.cc
    # return b'v=DKIM1; p=MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA6ZjT1KkiHbBZSRyNWo7sn5zv2+WmRFJjtv2K34ZZhY7d44i0yXbzH+sppr+MGyUD1hiNCvP52LPicPKjET/HDlOVOB5Qaa+L6zEaPasN7je9n1iIiGqtKS86SgWNGA8sSaz76qw+Chn2PcWOruQxAh/qDHMDeEjgHg5Nlso50R2lqS7iKqiR37g9ZvnG+4lLO+yIIrzip8o9SE+cc4jxrCa2vuzmm4k8CKtugNmV6qo0ycQRW8dPU5yBg54Bm98+ci+Uk/bGBBxe6Zc82ce5vSJppi9BboNK5yVlIviStkHKDSo7r3gwtumBQXgEVJ3MD8SR727a2KZqGGT8G2q3yQIDAQAB'

    #kolabnow.com
    # return b'v=DKIM1; p=MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA6cH7Zd5hMdUvSF5eCvsgkcLQYt1WaYxvy89E2k5a/Kf4mwnPp/e5WGLHMaZ9Br7U3LVj726axKk6VsBeW1xaPKj8vblLQ9k9XF0xBRU3PiHtRPZEKpQwrhtAvawWlM4erCjHPm9z807HgIXxZ/YXUK1RZXFt2mzwXvC6QZXOljokJvU08dkv7gLsHei7zTBbJMwrlGKo6/zMcwo64pPPKpmdcLoKRUgw1N1Lfas/coA7OJGiUhvnLVwvU66YavhWBPKgso63Hl1yn7o8MSB/wAmWTmEWgLkN8Z4UgO0zFxfwaUjBihyE3LuuUZFPioJ51vJiBX+i+NtlHAbIkarOBlbgqZpiuIt4ePl10AfyDJgSyMDCofsGyNnW6PXfGmbo4td7o19UfCO2dWpAS2DUwtWk74ncMW6AhFrGM/COuFmknxXP2rQVcflRPKoBxgUmsm8yuNO7GEN624mlVFSsJzCleJ3gIuUuA5x+VaWG5h1YjrFPZIEzuiC+Ki1ZTuxC4YNga233Q/P9ce7lqv2bI3rOjkJ2xVsSEhow+vXgnC8xwwVnWHnUPWiQG/ZUqin/YYMGrhGGkvhfRGitAJBvJ/kI30Nb2VRmSmHPzDLNqI0HceGpmar5lUQJz8L60fnYDe6cHf8FtTTD9wXujamY50Tw3XIrbhhIfuL5BzXokx0CAwEAAQ=='

def main():
    parser = argparse.ArgumentParser(
        description='Verify DKIM signature for email messages.',
        epilog="message to be verified follows commands on stdin")
    parser.add_argument('--index', metavar='N', type=int, default=0,
        help='Index of DKIM signature header to verify: default=0')
    parser.add_argument('-v', '--verbose', action='store_true',
        help='Verbose mode')
    args=parser.parse_args()
    if sys.version_info[0] >= 3:
        # Make sys.stdin a binary stream.
        sys.stdin = sys.stdin.detach()

    message = sys.stdin.read()
    # if args.verbose:
    import logging
    logger = logging.getLogger(__name__)
    logger.setLevel(logging.DEBUG)
    ch = logging.StreamHandler()
    ch.setLevel(logging.DEBUG)
    formatter = logging.Formatter('%(asctime)s - %(name)s - %(levelname)s - %(message)s')
    ch.setFormatter(formatter)
    logger.addHandler(ch)

    d = dkim.DKIM(message, logger=logger)
    # else:
    #     d = dkim.DKIM(message)

    res = d.verify(args.index,dnsfunc=dns_get_txt)
    if not res:
        print("signature verification failed")
        sys.exit(1)
    print("signature ok")


if __name__ == "__main__":
    main()
