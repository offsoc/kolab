#!/bin/bash

function check_success() {
  if [[ "$1" == "0" ]]; then
    echo "1";
  else
    echo "0";
  fi;
}

function checkout() {
    if [ ! -d "$1" ]; then
        git clone "$2" "$1" || exit
        pushd "$1" || exit
        git checkout "$3" || exit
        popd || exit
    fi
}

function pin_commit() {
    git ls-remote --exit-code -h "$1" "refs/heads/$2" | awk '{print $1}'
}

if [[ "$CACHE_REGISTRY" != "" ]]; then
    cat <<EOF >> /etc/containers/registries.conf
[[registry]]
prefix = "$CACHE_REGISTRY"
insecure = true
location = "$CACHE_REGISTRY"
EOF
fi

# This is the code that we are going to test
checkout kolab "$GIT_REMOTE" "$GIT_REF"
pushd kolab || exit


# This are the pinned commits that are going to be used for the base images
export KOLAB_GIT_REMOTE=https://git.kolab.org/source/kolab
export KOLAB_GIT_REF=$(pin_commit "$KOLAB_GIT_REMOTE" "dev/mollekopf")

export GIT_REMOTE_ROUNDCUBEMAIL=https://git.kolab.org/source/roundcubemail.git
export GIT_REF_ROUNDCUBEMAIL=$(pin_commit "$GIT_REMOTE_ROUNDCUBEMAIL" "dev/kolab-1.5")

export GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS=https://git.kolab.org/diffusion/RPK/roundcubemail-plugins-kolab.git
export GIT_REF_ROUNDCUBEMAIL_PLUGINS=$(pin_commit "$GIT_REMOTE_ROUNDCUBEMAIL_PLUGINS" "master")

export GIT_REMOTE_CHWALA=https://git.kolab.org/diffusion/C/chwala.git
export GIT_REF_CHWALA=$(pin_commit "$GIT_REMOTE_CHWALA" "dev/mollekopf")

export GIT_REMOTE_SYNCROTON=https://git.kolab.org/diffusion/S/syncroton.git
export GIT_REF_SYNCROTON=$(pin_commit "$GIT_REMOTE_SYNCROTON" "master")

export GIT_REMOTE_AUTOCONF=https://git.kolab.org/diffusion/AC/autoconf.git
export GIT_REF_AUTOCONF=$(pin_commit "$GIT_REMOTE_AUTOCONF" "master")

export GIT_REMOTE_IRONY=https://git.kolab.org/source/iRony.git
export GIT_REF_IRONY=$(pin_commit "$GIT_REMOTE_IRONY" "master")

export GIT_REMOTE_FREEBUSY=https://git.kolab.org/diffusion/F/freebusy.git
export GIT_REF_FREEBUSY=$(pin_commit "$GIT_REMOTE_FREEBUSY" "master")

export IMAP_GIT_REMOTE=https://git.kolab.org/source/cyrus-imapd
export IMAP_GIT_REF=$(pin_commit "$GIT_REMOTE_FREEBUSY" "dev/kolab-3.6")

# Execute
ci/testctl build
BUILD_RESULT=$(check_success $?)
ci/testctl lint
LINT_RESULT=$(check_success $?)
ci/testctl testrun
TESTRUN_RESULT=$(check_success $?)

# Publish test results
if [[ "$PROMETHEUS_PUSHGATEWAY" != "" ]]; then
    EPOCH=$(date +"%s")
    METRICS=$(
    cat <<EOF
kolab_ci_timestamp $EPOCH
# HELP kolab_ci_testsuite Displays whether or not the testsuite passed
# TYPE kolab_ci_testsuite gauge
kolab_ci_testsuite{host="$HOSTNAME", testsuite="build"} $BUILD_RESULT
kolab_ci_testsuite{host="$HOSTNAME", testsuite="lint"} $LINT_RESULT
kolab_ci_testsuite{host="$HOSTNAME", testsuite="testrun"} $TESTRUN_RESULT
EOF
)

    echo "Pushing result to $PROMETHEUS_PUSHGATEWAY"
    echo "$METRICS"
    echo "$METRICS" | curl -k --data-binary @- "$PROMETHEUS_PUSHGATEWAY"
fi

if [[ $TESTRUN_RESULT != "1" || $BUILD_RESULT != "1" || $LINT_RESULT != "1" ]]; then
    exit 1
fi
