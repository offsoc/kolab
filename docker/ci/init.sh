#!/bin/bash

if [[ "$CACHE_REGISTRY" != "" ]]; then
    cat <<EOF >> /etc/containers/registries.conf
[[registry]]
prefix = "$CACHE_REGISTRY"
insecure = true
location = "$CACHE_REGISTRY"
EOF
fi

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

checkout kolab "$GIT_REMOTE" "$GIT_REF"
pushd kolab || exit

ci/testctl build
BUILD_RESULT=$(check_success $?)
ci/testctl lint
LINT_RESULT=$(check_success $?)
ci/testctl testrun
TESTRUN_RESULT=$(check_success $?)

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
