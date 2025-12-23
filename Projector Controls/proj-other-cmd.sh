#!/bin/bash

# 1. Configuration
PORT="/dev/ttyUSB0"
BAUD="9600"

# 2. Initialize the port settings
# We do this before opening the FD to ensure the hardware is ready
sudo stty -F $PORT $BAUD raw -echo -echoe -echok -echoctl -echoke

# 3. Open the File Descriptor (FD)
# 'exec 3<>$PORT' opens FD 3 for both reading (<) and writing (>)
exec 3<>$PORT

# 4. Send the commands to FD 3
# Note the '>&3' syntax, which redirects output to our open FD
echo -e -n '\r' >&3
sleep 1
echo -e -n '*ltim=?#\r' >&3

# 5. Read the response from FD 3
# We use 'read -t' to set a timeout (in seconds) so the script doesn't
# hang if the device doesn't answer.
echo "Waiting for response..."
while read -t 2 -u 3 line; do
    # Strip carriage returns if they appear as ^M
    clean_line=$(echo "$line" | tr -d '\r')
    echo "Device Output: $clean_line"
done

# 6. Close the File Descriptor
exec 3>&-

echo "Done."
