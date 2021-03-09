#!/bin/env python3

# This script allows mass migrating pst files.
# Usage ./massmigrate.py
#       --input ./input/
#       --output ./output/
#       --password $password
#       --imap ssl://$domain:993 --dav https://$domain/iRony
#
# It requires an input and an output directory.
#
# The input directory contains files to import in the format:
# username@hostname.pst
#
# The output directory will receive all processed pst files.

import subprocess
import argparse
import glob
import shutil
import os
import time
from threading import Thread
from queue import Queue


def transform_file(file):
    dir_path, filename = os.path.split(os.path.realpath(file))

    removepst = False
    if '.zip' in file:
        username = filename.replace('.zip', '')
        parts = username.split('.')
        username = f"{parts[1]}.{parts[2]}@{parts[0].lower()}.domain.com"
        result = subprocess.run(f"unzip {filename}",
            shell=True,
            cwd=dir_path
        )
        if result.returncode != 0:
            return None, None, None, None
        removepst = True
        filename = f"{parts[1]}.{parts[2]}.pst"
    else:
        username = filename.replace('.pst', '')

    return dir_path, filename, username, removepst


def import_file(file, password, imap_uri, dav_uri, type_filter, type_blacklist):
    image = "migrate-s2i"
    start = time.time()

    dir_path, filename, username, removepst = transform_file(file)
    if not username:
        print(f"Failed to extract the file {file}")
        return False

    print("Starting " + username)

    DOCKER_OPTIONS = ' '.join([
        "--network=host", "--rm", "-ti",
        "-v", f"{dir_path}:/opt/app-root/input/",
        "-w", "/opt/app-root/src/kolab/src",
    ])

    cmdargs = [
        "./artisan", "migrate:userdata",
        f"--importpst=/opt/app-root/input/{filename}",
        f"--username={username} --password={password}",
        "--subscribe --debug",
        f"--davUrl={dav_uri}",
        f"--imapUrl={imap_uri}",
        "--exclude-target='Sync Issues (This computer only)'",
        "--exclude-target='Drafts (This computer only)'",
        "--exclude-target='Sync Issues*'",
        "--exclude-target='Recoverable Items*'",
    ]

    if type_filter:
        cmdargs.append(f"--type-filter='{type_filter}'")

    if type_blacklist:
        cmdargs.append(f"--type-blacklist='{type_blacklist}'")

    CMD = ' '.join(cmdargs)

    with open(f"output/{username}.log", 'w') as logfile:
        result = subprocess.run(f"docker run {DOCKER_OPTIONS} {image} {CMD}",
                                shell=True,
                                text=True,
                                stderr=subprocess.STDOUT,
                                stdout=logfile)

        executionTime = time.time() - start
        print(f"Finished {username} in {executionTime}s")
        if removepst:
            os.remove(f"{dir_path}/{filename}")
        return not result.returncode
    return 1


def main():
    parser = argparse.ArgumentParser("usage: %prog [options]")
    parser.add_argument("--input", help="Input directory")
    parser.add_argument("--output", help="Output directory")
    parser.add_argument("--password", help="User password to use for all files")
    parser.add_argument("--imap", help="IMAP URI")
    parser.add_argument("--dav", help="DAV URI")
    parser.add_argument("--typefilter", help="Folder type whitelist (not a list)")
    parser.add_argument("--typeblacklist", help="Folder type blacklist (not a list)")
    options = parser.parse_args()

    input_dir = os.path.expanduser(options.input)
    output_dir = os.path.expanduser(options.output)

    q = Queue()
    for file in glob.glob(f"{input_dir}*"):
        q.put(file)

    def worker():
        while True:
            file = q.get()
            if import_file(file, options.password, options.imap, options.dav, options.typefilter, options.typeblacklist):
                shutil.move(file, output_dir + os.path.basename(file))
            else:
                print(f"Failed to import {file}")
            q.task_done()

    for _i in range(3):
        Thread(target=worker, daemon=True).start()
    q.join()
    print("Processing finished")


if __name__ == "__main__":
    main()
