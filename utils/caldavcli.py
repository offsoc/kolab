#!/bin/env python3

"""
caldavcli.py
    --host apps.kolabnow.com
    --user user@kolab.org --password Secret
    --verbose
    list --folder Calendar

"""

import argparse
import base64
import http.client
import urllib.parse
import ssl
import xml.etree.ElementTree as ET
from xml.dom import minidom


def http_request(url, method, params=None, headers=None, body=None):
    """
        Perform an HTTP request.
    """

    parsed_url = urllib.parse.urlparse(url)
    if url.startswith('https://'):
        conn = http.client.HTTPSConnection(parsed_url.netloc, 443, context = ssl._create_unverified_context())
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

    # Assemble a relative url
    url = urllib.parse.urlunsplit(["", "", parsed_url.path, parsed_url.query, parsed_url.fragment])
    print(f"Requesting {url} From {parsed_url.netloc} Using {method}")
    conn.request(method, url, body, headers)
    response = conn.getresponse()

    # Handle redirects
    if response.status in (301, 302):
        # print("Following redirect ", response.getheader('location', ''))
        return http_request(
            urllib.parse.urljoin(url, response.getheader('location', '')),
            method,
            params,
            headers,
            body)

    if response.status not in (200, 207):
        print("  ", "Status", response.status)
        print("  ", response.read().decode())

    return response


def basic_auth_headers(username, password):
    user_and_pass = base64.b64encode(
        f"{username}:{password}".encode("ascii")
    ).decode("ascii")

    return {
        "Authorization": "Basic {}".format(user_and_pass)
    }


class CalDAV:
    def __init__(self, options):
        self.host = options.host
        self.username = options.user
        self.password = options.password
        self.verbose = options.verbose

        if hasattr(options, 'folder') and options.folder:
            self.folder = options.folder
        else:
            self.folder = None

        if hasattr(options, 'href') and options.href:
            self.href = options.href
        else:
            self.href = None

    def send_request(self, url, method, body = None):
        headers = {
            "Content-Type": "application/xml; charset=utf-8",
            "Depth": "infinity",
            **basic_auth_headers(self.username, self.password)
        }

        return http_request(
            url,
            method,
            None,
            headers,
            body
        )

    def check(self):
        body = '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>'

        response = self.send_request(
            f"https://{self.host}/principals/{self.username}/",
            "PROPFIND",
            body
        )

        success = response.status == 207
        if not success:
            print("=> Error: Caldav is not available")

        if self.verbose or not success:
            print("  ", "Status", response.status)
            print("  ", response.read().decode())

        return success

    def show(self, href):
        body = """
        <c:calendar-multiget xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav" xmlns:cs="https://calendarserver.org/ns/">
            <d:prop>
                <d:getetag />
                <c:calendar-data />
            </d:prop>
            <d:href>{href}</d:href>
        </c:calendar-multiget>
        """.replace('    ', '').replace('\n', '').format(href=href)

        response = self.send_request(
            f"https://{self.host}{href}",
            "REPORT",
            body
        )

        assert response.status == 207

        result = response.read().decode()

        root = ET.fromstring(result)
        print(minidom.parseString(ET.tostring(root)).toprettyxml(indent="   "))

    def fetch(self, href):
        response = self.send_request(
            f"https://{self.host}{href}",
            "PROPFIND"
        )

        assert response.status == 207

        result = response.read().decode()

        root = ET.fromstring(result)
        if self.verbose:
            print(minidom.parseString(ET.tostring(root)).toprettyxml(indent="   "))

    def list(self):
        body = '<d:propfind xmlns:d="DAV:" xmlns:cs="https://calendarserver.org/ns/"><d:prop><d:resourcetype /><d:displayname /></d:prop></d:propfind>'

        response = self.send_request(
            f"https://{self.host}/calendars/{self.username}/",
            "PROPFIND",
            body
        )

        assert response.status == 207

        result = response.read().decode()

        root = ET.fromstring(result)
        if self.verbose:
            print(minidom.parseString(ET.tostring(root)).toprettyxml(indent="   "))

        xmlns = "DAV:"

        for add in root.findall(f".//{{{xmlns}}}response"):
            displayName = add.find(f".//{{{xmlns}}}displayname").text
            href = add.find(f".//{{{xmlns}}}href").text
            print("DisplayName", displayName)
            print("href", href)

            if self.folder and displayName == self.folder:
                self.fetch(href)
            if self.href and href == self.href:
                self.fetch(href)


def main():
    parser = argparse.ArgumentParser()

    parser.add_argument("--host", help="Host")
    parser.add_argument("--user", help="Username")
    parser.add_argument("--password", help="User password")
    parser.add_argument("--verbose", action='store_true', help="Verbose output")

    subparsers = parser.add_subparsers()

    parser_list = subparsers.add_parser('list')
    parser_list.add_argument("--folder", help="Folder")
    parser_list.add_argument("--href", help="Match by href")
    parser_list.set_defaults(func=lambda args: CalDAV(args).list())

    parser_list = subparsers.add_parser('show')
    parser_list.add_argument("--href", help="Match by href")
    parser_list.set_defaults(func=lambda args: CalDAV(args).show(args.href))

    parser_check = subparsers.add_parser('check')
    parser_check.set_defaults(func=lambda args: CalDAV(args).check())

    options = parser.parse_args()

    if 'func' in options:
        options.func(options)


if __name__ == "__main__":
    main()
