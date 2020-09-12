#!/bin/bash

sudo stty -F /dev/ttyUSB0 9600 raw -echo -echoe -echok -echoctl -echoke
echo -e -n '\r' > /dev/ttyUSB0
sleep 1
echo -e -n '*pow=off#\r' > /dev/ttyUSB0
