#!/bin/env python3

"""
kolabendpointtester.py
    --host apps.kolabnow.com
    --user user@kolab.org
    --password Secret
    --dav https://apps.kolabnow.com
    --fb https://apps.kolabnow.com/calendars/user@kolab.org/6f552d35-95c4-41f6-a7d2-cfd02dd867db
"""

import sys
import traceback
import socket
import ssl
import argparse
from base64 import b64encode
import http.client
import urllib.parse
import dns.resolver

SSLNOVERIFY = False

def print_assertion_failure():
    """
        Print an error message about a failed assertion
    """
    _, _, trace = sys.exc_info()
    tb_info = traceback.extract_tb(trace)
    _filename, line, _func, text = tb_info[-1]
    print(f"  ERROR assertion on line {line} failed on {text}")


def http_request(url, method, params=None, headers=None, body=None):
    """
        Perform an HTTP request.
    """

    parsed_url = urllib.parse.urlparse(url)
    # print("Connecting to ", parsed_url.netloc)
    if url.startswith('https://'):
        conn = http.client.HTTPSConnection(parsed_url.netloc, 443, context = (ssl._create_unverified_context() if SSLNOVERIFY else None))
    else:
        conn = http.client.HTTPConnection(parsed_url.netloc, 80)

    if params is None:
        params = {}

    if headers is None:
        headers = {
            "Content-Type": "application/x-www-form-urlencoded; charset=utf-8"
        }

    if body is None:
        body = urllib.parse.urlencode(params)

    print("Requesting", parsed_url.path, "From", parsed_url.netloc)
    conn.request(method, parsed_url.geturl(), body, headers)
    response = conn.getresponse()

    # Handle redirects
    if response.status in (301, 302,):
        print("Following redirect ", response.getheader('location', ''))
        return http_request(
            urllib.parse.urljoin(url, response.getheader('location', '')),
            method,
            params,
            headers,
            body)

    return response


def basic_auth_headers(username, password):
    user_and_pass = b64encode(
        f"{username}:{password}".encode("ascii")
    ).decode("ascii")

    return {
        "Authorization": "Basic {}".format(user_and_pass)
    }


def try_get(name, url, verbose, headers = None, body = None):
    response = http_request(
        url,
        "GET",
        None,
        headers,
        body
    )
    success = response.status == 200
    if not success:
        print(f"=> Error: {name} is not available")

    if verbose or not success:
        print("  ", "Status", response.status)
        print("  ", response.read().decode())

    return success


def discover_principal(url, username, password, verbose = False):
    body = '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>'

    headers = {
        "Content-Type": "application/xml; charset=utf-8",
        "Depth": "infinity",
        **basic_auth_headers(username, password)
    }

    response = http_request(
        f"{url}/principals/{username}/",
        "PROPFIND",
        None,
        headers,
        body
    )

    success = response.status == 207
    if not success:
        print("=> Error: Caldav is not available")

    if verbose or not success:
        print("  ", "Status", response.status)
        print("  ", response.read().decode())

    return success


def test_freebusy_authenticated(url, username, password, verbose = False):
    # Request our own freebusy authenticated
    return try_get("Authenticated Freebusy", f"{url}/{username}.ifb", verbose, headers = basic_auth_headers(username, password))


def test_freebusy_unauthenticated(url, username, password, verbose = False):
    return try_get("Unauthenticated Freebusy", f"{url}/{username}.ifb", verbose)


def test_autoconfig(host, username, password, verbose = False):
    if not try_get("Autoconf .well-known", f"https://{host}/.well-known/autoconfig/mail/config-v1.1.xml?emailaddress={username}", verbose):
        return False
    if not try_get("Autoconf /mail", f"https://{host}/mail/config-v1.1.xml?emailaddress={username}", verbose):
        return False

# TODO
# def test_007_well_known_outlook():
#     body = '''<Autodiscover \
# xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006">
#     <Request>
#       <EMailAddress>admin@example.local</EMailAddress>
#       <AcceptableResponseSchema>
#          http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a
#        </AcceptableResponseSchema>
#     </Request>
# </Autodiscover>'''

#     headers = {
#         "Content-Type": "text/xml; charset=utf-8"
#     }
#     response = http_post(
#         "https://kolab-vanilla.{}.local/autodiscover/autodiscover.xml".format(hostname),
#         None,
#         headers,
#         body
#     )
#     assert response.status == 200
#     data = response.read()
#     decoded = codecs.decode(data)
#     # Sanity check of the data
#     assert '<Server>example.local</Server>' in decoded
#     assert "admin@example.local" in decoded

#     # Ensure the alternative urls also work
#     assert http_post(
#         "https://kolab-vanilla.{}.local/Autodiscover/Autodiscover.xml".format(hostname),
#         None,
#         headers,
#         body
#     ).status == 200

#     assert http_post(
#         "https://kolab-vanilla.{}.local/AutoDiscover/AutoDiscover.xml".format(hostname),
#         None,
#         headers,
#         body
#     ).status == 200


def test_autodiscover_activesync(host, username, password, verbose = False):
    """
    We expect something along the lines of

    <?xml version="1.0" encoding="UTF-8"?>
    <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006">
    <Response xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006">
        <User>
        <DisplayName>User Name</DisplayName>
        <EMailAddress>user@example.com</EMailAddress>
        </User>
        <Action>
        <Settings>
            <Server>
            <Type>MobileSync</Type>
            <Url>https://kolab.example.com/Microsoft-Server-ActiveSync</Url>
            <Name>https://kolab.example.com/Microsoft-Server-ActiveSync</Name>
            </Server>
        </Settings>
        </Action>
    </Response>
    </Autodiscover>
    """

    body = f'''<Autodiscover \
xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006">
    <Request>
      <EMailAddress>{username}</EMailAddress>
      <AcceptableResponseSchema>
         http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006
       </AcceptableResponseSchema>
    </Request>
</Autodiscover>'''

    headers = {
        "Content-Type": "text/xml; charset=utf-8",
        **basic_auth_headers(username, password)
    }

    response = http_request(
        f"https://{host}/autodiscover/autodiscover.xml",
        "POST",
        None,
        headers,
        body
    )

    success = response.status == 200
    data = response.read().decode()
    if success:
        try:
            # Sanity check of the data
            assert "<Type>MobileSync</Type>" in data
            assert f"<Url>https://{host}/Microsoft-Server-ActiveSync</Url>" in data
            assert username in data
        except AssertionError:
            print_assertion_failure()
            success = False

    if not success:
        print("=> Error: Activesync autodiscover is not available")

    if verbose or not success:
        print("  ", "Status", response.status)
        print("  ", data)

    return success


def test_activesync(host, username, password, verbose = False):
    headers = {
        "Host": host,
        **basic_auth_headers(username, password)
    }

    response = http_request(
        f"https://{host}/Microsoft-Server-ActiveSync",
        "OPTIONS",
        None,
        headers,
        None
    )

    success = response.status == 200
    data = response.read().decode()
    if success:
        try:
            assert response.getheader('MS-Server-ActiveSync', '')
            assert '14.1' in response.getheader('MS-ASProtocolVersions', '')
            assert 'FolderSync' in response.getheader('MS-ASProtocolCommands', '')
        except AssertionError:
            print_assertion_failure()
            success = False

    if not success:
        print("=> Error: Activesync is not available")

    if verbose or not success:
        print("  ", "Status", response.status)
        print("  ", data)

    return success


def test_dns(host, verbose = False):
    success = True
    try:
        answers = dns.resolver.resolve(host, 'MX')
        for rdata in answers:
            print('  MX Host', rdata.exchange, 'has preference', rdata.preference)
    except dns.resolver.NXDOMAIN:
        success = False
        print("  ERROR on MX record")
    except dns.resolver.NoAnswer:
        success = False
        print("  ERROR on MX record")

    try:
        answers = dns.resolver.resolve(f"autodiscover.{host}", 'CNAME')
        for rdata in answers:
            print('  autodiscover CNAME', rdata.target)
    except dns.resolver.NXDOMAIN:
        success = False
        print("  ERROR on autodiscover. CNAME entry")
    except dns.resolver.NoAnswer:
        success = False
        print("  ERROR on autodiscover. CNAME entry")

    srv_records = [
        f"_autodiscover._tcp.{host}",
        f"_caldav._tcp.{host}",
        f"_caldavs._tcp.{host}",
        f"_carddav._tcp.{host}",
        f"_carddavs._tcp.{host}",
        f"_imap._tcp.{host}",
        f"_imaps._tcp.{host}",
        f"_sieve._tcp.{host}",
        f"_submission._tcp.{host}",
        f"_webdav._tcp.{host}",
        f"_webdavs._tcp.{host}",
    ]

    for record in srv_records:
        try:
            answers = dns.resolver.resolve(record, 'SRV')
            for rdata in answers:
                print("  ", record, rdata.target)
        except dns.resolver.NXDOMAIN:
            success = False
            print("  ERROR on record", record)
        except dns.resolver.NoAnswer:
            success = False
            print("  ERROR on record", record)

    if not success:
        print(f"=> Error: Dns entires on {host} not available")

    return success


def test_email_dns(host, verbose = False):
    success = True

    srv_records = [
        f"_autodiscover._tcp.{host}"
    ]

    for record in srv_records:
        try:
            answers = dns.resolver.resolve(record, 'SRV')
            for rdata in answers:
                print("  ", record, rdata.target)
        except dns.resolver.NXDOMAIN:
            success = False
            print("  ERROR on record", record)
        except dns.resolver.NoAnswer:
            success = False
            print("  ERROR on record", record)

    if not success:
        print(f"=> Error: Dns entires on {host} not available")

    return success

def test_certificates(host, davhost, imaphost, verbose):
    success = True
    hosts = [
        (host, 443),
    ]

    if davhost:
        hosts.append((urllib.parse.urlparse(davhost).netloc, 443))
    if imaphost:
        hosts.append((imaphost, 993))
        hosts.append((imaphost, 587))

    context = ssl.create_default_context()

    for hosttuple in hosts:
        hostname, _port = hosttuple
        try:
            conn = context.wrap_socket(socket.socket(socket.AF_INET), server_hostname=hostname)
            conn.connect(hosttuple)
            cert = conn.getpeercert()
            if verbose:
                print(cert)
        except OSError as err:
            print("  ERROR on peer", hosttuple, err)
            success = False

    if not success:
        print("=> Error: Not all certificates are valid")

    return success


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--host", help="Host")
    parser.add_argument("--username", help="Username")
    parser.add_argument("--password", help="User password")
    parser.add_argument("--imap", help="IMAP URI")
    parser.add_argument("--dav", help="DAV URI")
    parser.add_argument("--fb", help="Freebusy url as displayed in roundcube")
    parser.add_argument("--verbose", action='store_true', help="Verbose output")
    options = parser.parse_args()

    if options.dav:
        if discover_principal(options.dav, options.username, options.password, options.verbose):
            print("=> Caldav is available")

        if discover_principal("https://" + options.host + "/.well-known/caldav", options.username, options.password, options.verbose):
            print("=> Caldav on .well-known/caldav is available")

    if test_autoconfig(options.host, options.username, options.password, options.verbose):
        print("=> Autoconf available")

    if test_autodiscover_activesync(options.host, options.username, options.password, options.verbose):
        print("=> Activesync Autodsicovery available")

    if test_activesync(options.host, options.username, options.password, options.verbose):
        print("=> Activesync available")

    if options.fb and test_freebusy_authenticated(options.fb, options.username, options.password, options.verbose):
        print("=> Authenticated Freebusy is available")

    # We rely on the activesync test to have generated the token for unauthenticated access.
    if options.fb and test_freebusy_unauthenticated(options.fb, options.username, options.password, options.verbose):
        print("=> Unauthenticated Freebusy is available")

    if test_dns(options.host, options.verbose):
        print(f"=> DNS entries on {options.host} available")

    userhost = options.username.split('@')[1]
    if test_email_dns(userhost, options.verbose):
        print(f"=> DNS entries on {userhost} available")

    if test_certificates(options.host, options.dav, options.imap, options.verbose):
        print("=> All certificates are valid")


if __name__ == "__main__":
    main()
