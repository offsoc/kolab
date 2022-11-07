#!/bin/env python3

"""
activesynccli.py
    --host apps.kolabnow.com
    --devicetype WindowsOutlook15
    --deviceid windowsascli
    --user user@kolab.org --password Secret
    --verbose
    list --folder INBOX

# Dependencies

    dnf install libwbxml-devel
    pip install --global-option=build_ext --global-option="-I/usr/include/libwbxml-1.0/wbxml/" git+https://github.com/Apheleia-IT/python-wbxml#egg=wbxml

"""

import argparse
import base64
import http.client
import urllib.parse
import struct
import xml.etree.ElementTree as ET
import ssl
import wbxml


def decode_timezone(tz):
    decoded = base64.b64decode(tz)
    bias, standardName, standardDate, standardBias, daylightName, daylightDate, daylightBias = struct.unpack('i64s16si64s16si', decoded)
    print(f"  TimeZone bias: {bias}min")
    print(f"  Standard Name: {standardName.decode()}")
    year, month, day, week, hour, minute, second, millis = struct.unpack('hhhhhhhh', standardDate)
    print(f"  Standard Date: Year: {year} Month: {month} Day: {day} Week: {week} Hour: {hour} Minute: {minute} Second: {second} Millisecond: {millis}")
    print(f"  Daylight Name: {daylightName.decode()}")
    year, month, day, week, hour, minute, second, millis = struct.unpack('hhhhhhhh', daylightDate)
    print(f"  Daylight Date: Year: {year} Month: {month} Day: {day} Week: {week} Hour: {hour} Minute: {minute} Second: {second} Millisecond: {millis}")
    print(f"  Daylight Bias: {daylightBias}min")
    print()


def http_request(url, method, params=None, headers=None, body=None):
    """
        Perform an HTTP request.
    """

    # print(url)
    parsed_url = urllib.parse.urlparse(url)
    # print("Connecting to ", parsed_url.netloc)
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

    # print("Requesting", parsed_url.geturl(), "From", parsed_url.netloc)
    conn.request(method, parsed_url.geturl(), body, headers)
    response = conn.getresponse()

    # Handle redirects
    if response.status in (301, 302,):
        # print("Following redirect ", response.getheader('location', ''))
        return http_request(
            urllib.parse.urljoin(url, response.getheader('location', '')),
            method,
            params,
            headers,
            body)

    if not response.status == 200:
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


class ActiveSync:
    def __init__(self, options):
        self.host = options.host
        self.username = options.user
        self.password = options.password
        self.verbose = options.verbose

        if options.deviceid:
            self.deviceid = options.deviceid
        else:
            self.deviceid = 'v140Device'

        if options.devicetype:
            self.devicetype = options.devicetype
        else:
            self.devicetype = 'iphone'

        if hasattr(options, 'folder') and options.folder:
            self.folder = options.folder
        else:
            self.folder = None


    def send_request(self, command, request, extra_args = None):
        body = wbxml.xml_to_wbxml(request)

        headers = {
            "Host": self.host,
            **basic_auth_headers(self.username, self.password)
        }

        headers.update(
            {
                "Content-Type": "application/vnd.ms-sync.wbxml",
                'MS-ASProtocolVersion': "14.0",
            }
        )

        if extra_args is None:
            extra_args = ""

        return http_request(
            f"https://{self.host}/Microsoft-Server-ActiveSync?Cmd={command}&User={self.username}&DeviceId={self.deviceid}&DeviceType={self.devicetype}{extra_args}",
            "POST",
            None,
            headers,
            body
        )


    def check(self):
        headers = {
            "Host": self.host,
            **basic_auth_headers(self.username, self.password)
        }

        response = http_request(
            f"https://{self.host}/Microsoft-Server-ActiveSync",
            "OPTIONS",
            None,
            headers,
            None
        )

        success = response.status == 200
        data = response.read().decode()
        if not success:
            print("=> Error: Activesync is not available")
        else:
            # Sanity check of the data
            assert response.getheader('MS-Server-ActiveSync', '')
            assert '14.1' in response.getheader('MS-ASProtocolVersions', '')
            assert 'FolderSync' in response.getheader('MS-ASProtocolCommands', '')

        if self.verbose or not success:
            print("  ", "Status", response.status)
            print("  ", data)

        return success


    def fetch(self, collection_id, sync_key = 0):
        request = """
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>{sync_key}</SyncKey>
                    <CollectionId>{collection_id}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <WindowSize>512</WindowSize>
                    <Options>
                        <FilterType>0</FilterType>
                        <MIMESupport>2</MIMESupport>
                        <MIMETruncation>8</MIMETruncation>
                        <BodyPreference xmlns="uri:AirSyncBase">
                            <Type>4</Type>
                            <AllOrNone>1</AllOrNone>
                        </BodyPreference>
                    </Options>
                </Collection>
            </Collections>
            <WindowSize>512</WindowSize>
        </Sync>
        """.replace('    ', '').replace('\n', '')

        response = self.send_request('Sync', request.format(collection_id=collection_id, sync_key=sync_key))

        assert response.status == 200

        data = response.read()
        if not data:
            if self.verbose:
                print("Empty response, no changes on server")
            return

        result = wbxml.wbxml_to_xml(data)

        if self.verbose:
            print(result)

        root = ET.fromstring(result)
        xmlns = "http://synce.org/formats/airsync_wm5/airsync"
        sync_key = root.find(f".//{{{xmlns}}}SyncKey").text
        more_available = (len(root.findall(f".//{{{xmlns}}}MoreAvailable")) == 1)
        if self.verbose:
            print("Current SyncKey:", sync_key)

        for add in root.findall(f".//{{{xmlns}}}Add"):
            serverId = add.find(f"{{{xmlns}}}ServerId").text
            print("  ServerId", serverId)
            applicationData = add.find(f"{{{xmlns}}}ApplicationData")

            calxmlns = "http://synce.org/formats/airsync_wm5/calendar"
            subject = applicationData.find(f"{{{calxmlns}}}Subject")
            if subject is not None:
                print("  Subject", subject.text)
            startTime = applicationData.find(f"{{{calxmlns}}}StartTime")
            if startTime is not None:
                print("  StartTime", startTime.text)
            timeZone = applicationData.find(f"{{{calxmlns}}}TimeZone")
            if timeZone is not None:
                decode_timezone(timeZone.text)
                #the dates are encoded like so: vstdyear/vstdmonth/vstdday/vstdweek/vstdhour/vstdminute/vstdsecond/vstdmillis
                decoded = base64.b64decode(timeZone.text)
                bias, standardName, standardDate, standardBias, daylightName, daylightDate, daylightBias = struct.unpack('i64s16si64s16si', decoded)
                print(f"  TimeZone bias: {bias}min")
            print("")


        print("\n")

        # Fetch after the initial sync
        if sync_key == "1":
            print("after initial sync", collection_id, sync_key)
            self.fetch(collection_id, sync_key)

        # Fetch more
        if more_available:
            print("more available")
            print(root.findall(f".//{{{xmlns}}}MoreAvailable"))
            self.fetch(collection_id, sync_key)



    def list(self):
        request = """
            <?xml version="1.0" encoding="utf-8"?>
            <!DOCTYPE ActiveSync PUBLIC "-//MICROSOFT//DTD ActiveSync//EN" "http://www.microsoft.com/">
            <FolderSync xmlns="FolderHierarchy:">
                <SyncKey>0</SyncKey>
            </FolderSync>
        """.replace('    ', '').replace('\n', '')

        response = self.send_request('FolderSync', request)

        assert response.status == 200

        result = wbxml.wbxml_to_xml(response.read())

        if self.verbose:
            print(result)

        root = ET.fromstring(result)
        xmlns = "http://synce.org/formats/airsync_wm5/folderhierarchy"
        sync_key = root.find(f".//{{{xmlns}}}SyncKey").text
        if self.verbose:
            print("Current SyncKey:", sync_key)

        for add in root.findall(f".//{{{xmlns}}}Add"):
            displayName = add.find(f"{{{xmlns}}}DisplayName").text
            serverId = add.find(f"{{{xmlns}}}ServerId").text
            print("ServerId", serverId)
            print("DisplayName", displayName)

            if self.folder and displayName == self.folder:
                self.fetch(serverId)



def main():
    parser = argparse.ArgumentParser()

    parser.add_argument("--host", help="Host")
    parser.add_argument("--user", help="Username")
    parser.add_argument("--password", help="User password")
    parser.add_argument("--verbose", action='store_true', help="Verbose output")
    parser.add_argument("--deviceid", help="Device identifier ")
    parser.add_argument("--devicetype", help="devicetype (WindowsOutlook15, iphone)")

    subparsers = parser.add_subparsers()

    parser_list = subparsers.add_parser('decode_timezone')
    parser_list.add_argument("timezone", help="Base64 encoded timezone string ('Lv///0lyYW....///w==') ")
    parser_list.set_defaults(func=lambda args: decode_timezone(args.timezone))

    parser_list = subparsers.add_parser('list')
    parser_list.add_argument("--folder", help="Folder")
    parser_list.set_defaults(func=lambda args: ActiveSync(args).list())

    parser_check = subparsers.add_parser('check')
    parser_check.set_defaults(func=lambda args: ActiveSync(args).check())

    options = parser.parse_args()

    if 'func' in options:
        options.func(options)


if __name__ == "__main__":
    main()
