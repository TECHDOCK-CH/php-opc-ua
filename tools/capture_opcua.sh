#!/bin/bash
# Capture OPC UA traffic on localhost:4840

set -e

CAPTURE_FILE="opcua_capture_$(date +%Y%m%d_%H%M%S).pcap"

echo "Starting tcpdump capture on lo interface, port 4840..."
echo "Capture file: $CAPTURE_FILE"
echo ""
echo "In another terminal, run your OPC UA client."
echo "Press Ctrl+C to stop capture."
echo ""

sudo tcpdump -i lo -w "$CAPTURE_FILE" 'port 4840' -v

echo ""
echo "Capture saved to: $CAPTURE_FILE"
echo "Analyze with: tshark -r $CAPTURE_FILE -V -x"
