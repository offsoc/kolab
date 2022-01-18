#!/bin/bash

# Throttle local network connections

set -x

# Requires kernel-modules-extra (and perhaps kernel-debug-modules-extra) and a reboot
sudo modprobe sch_netem || exit 1

INTERFACE=$(sudo route | grep -m 1 '^default' | grep -o '[^ ]*$' | tr -d '\n')

if [[ $1 == "stop" ]]
then
    echo "Stopping"
    sudo tc qdisc del dev $INTERFACE root
    sudo tc qdisc del dev $INTERFACE ingress
    sudo tc qdisc del dev ifb0 root
    exit 0
fi

DOWN=500
UP=500
HALFWAYRTT=10

echo "Throttling $INTERFACE Up $UP Down $DOWN RTT/2 $HALFWAYRTT"
# Setup:
# This creates a virtual ifb interface to redirect ingress traffic over it,
# so we can then apply egress rules to the mirrored ingress traffic.
# This allows to use the more flexible egress rules for ingress traffic, instead of the more limited tc ingress filters.
sudo modprobe ifb || exit 1
sudo ip link set dev ifb0 up
sudo tc qdisc add dev $INTERFACE ingress
sudo tc filter add dev $INTERFACE parent ffff: protocol ip u32 match u32 0 0 flowid 1:1 action mirred egress redirect dev ifb0

# Set bandwith
sudo tc qdisc add dev ifb0 root handle 1:0 netem delay ${HALFWAYRTT}ms rate ${DOWN}kbit
sudo tc qdisc add dev $INTERFACE root handle 1:0 netem delay ${HALFWAYRTT}ms rate ${UP}kbit
