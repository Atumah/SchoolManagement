#!/bin/bash
# ============================================
# Script: Configure Network
# Purpose: Sets static IP address for Linux client
# Usage: Run as root or with sudo
# ============================================

set -e

echo "========================================"
echo "Configuring Network"
echo "========================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: This script must be run as root or with sudo"
    exit 1
fi

# Configuration
IP_ADDRESS="192.168.1.21"
GATEWAY="192.168.1.1"
DNS_SERVER="192.168.1.10"
INTERFACE="eth0"

echo "Network Configuration:"
echo "  IP Address: $IP_ADDRESS"
echo "  Gateway: $GATEWAY"
echo "  DNS: $DNS_SERVER"
echo "  Interface: $INTERFACE"
echo ""
read -p "Continue? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Cancelled."
    exit 0
fi

echo ""
echo "Step 1: Detecting network interface..."
# Try to detect interface
if [ -d /sys/class/net/ens33 ]; then
    INTERFACE="ens33"
elif [ -d /sys/class/net/enp0s3 ]; then
    INTERFACE="enp0s3"
elif [ -d /sys/class/net/eth0 ]; then
    INTERFACE="eth0"
else
    echo "  ⚠ Could not auto-detect interface"
    read -p "Enter interface name: " INTERFACE
fi

echo "  Using interface: $INTERFACE"

echo ""
echo "Step 2: Creating Netplan configuration..."

# Backup existing config
if [ -f /etc/netplan/01-netcfg.yaml ]; then
    cp /etc/netplan/01-netcfg.yaml /etc/netplan/01-netcfg.yaml.backup
fi

# Create new config
cat > /etc/netplan/01-netcfg.yaml <<EOF
network:
  version: 2
  renderer: networkd
  ethernets:
    $INTERFACE:
      addresses:
        - $IP_ADDRESS/24
      gateway4: $GATEWAY
      nameservers:
        addresses:
          - $DNS_SERVER
EOF

echo "  ✓ Netplan configuration created"

echo ""
echo "Step 3: Applying network configuration..."
netplan apply

if [ $? -eq 0 ]; then
    echo "  ✓ Network configuration applied"
else
    echo "  ✗ Failed to apply network configuration"
    exit 1
fi

echo ""
echo "Step 4: Testing network connectivity..."
if ping -c 1 "$GATEWAY" > /dev/null 2>&1; then
    echo "  ✓ Gateway reachable"
else
    echo "  ✗ Cannot reach gateway"
fi

if ping -c 1 "$DNS_SERVER" > /dev/null 2>&1; then
    echo "  ✓ DNS server reachable"
else
    echo "  ✗ Cannot reach DNS server"
fi

echo ""
echo "========================================"
echo "✓ Network configured!"
echo "========================================"
echo ""
echo "Network settings:"
ip addr show "$INTERFACE" | grep "inet "
echo ""


