#!/bin/bash
set -e
git clone https://git.kolab.org/source/kolab.git
pushd kolab
ci/testctl testrun
