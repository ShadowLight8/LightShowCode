#!/bin/bash

PORT="/dev/ttyUSB0"
BAUD="9600"

# Status variables
STATE="UNKNOWN"

# 1. Initialize port
sudo stty -F $PORT $BAUD raw -echo -echoe -echok -echoctl -echoke

# 2. Open File Descriptor
exec 3<>$PORT

# 3. Send commands
echo -e -n '\r' >&3
sleep 1
echo -e -n '*pow=?#\r' >&3

# 4. Parse the response
# We use a 2-second timeout for the read
while read -t 2 -u 3 line; do
    # Remove Carriage Return characters (^M)
    clean_line=$(echo "$line" | tr -d '\r')

    case "$clean_line" in
        "*POW=ON#")
            STATE="ON"
            break
            ;;
        "*POW=OFF#")
            STATE="OFF"
            break
            ;;
        "Block item")
            STATE="COOLDOWN"
            break
            ;;
        *)
            # Ignore echoes or other noise, but keep loop alive until timeout
            # echo $clean_line
            continue
            ;;
    esac
done

# 5. Close File Descriptor
exec 3>&-

# 6. Final Output and Exit Codes
if [ "$STATE" == "ON" ]; then
    echo "Projector is ON"
    exit 0
elif [ "$STATE" == "OFF" ]; then
    echo "Projector is OFF"
    exit 2
elif [ "$STATE" == "COOLDOWN" ]; then
    echo "Projector is in POWERUP or COOLDOWN"
    exit 3
else
    echo "Projector is UNRESPONSIVE"
    exit 1
fi
