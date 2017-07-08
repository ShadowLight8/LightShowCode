#!/bin/bash

cpu=$(</sys/class/thermal/thermal_zone0/temp)
gpu=$(/opt/vc/bin/vcgencmd measure_temp)
echo "CPU @ $(echo 'scale=1;'$cpu/1000 | bc)"$'\xc2\xb0'"C"
echo "GPU @ ${gpu:5:-2}"$'\xc2\xb0'"C"
