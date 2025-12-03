#!/bin/bash
# ============================================
# Script: Mount Network Shares
# Purpose: Mounts Private and General network drives
# Usage: Run as root or with sudo
# ============================================

set -e

echo "========================================"
echo "Mounting Network Shares"
echo "========================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: This script must be run as root or with sudo"
    exit 1
fi

# Configuration
SERVER="MORNINGSTAR-DC01"
PRIVATE_SHARE="//$SERVER/Private"
GENERAL_SHARE="//$SERVER/General"
MOUNT_PRIVATE="/mnt/private"
MOUNT_GENERAL="/mnt/general"
CREDENTIALS_FILE="/etc/samba/credentials"

echo "Server: $SERVER"
echo "Private Share: $PRIVATE_SHARE"
echo "General Share: $GENERAL_SHARE"
echo ""

# Get credentials
read -p "Enter domain username (e.g., teacher1): " DOMAIN_USER
read -p "Enter domain password: " -s DOMAIN_PASS
echo ""

DOMAIN="MORNINGSTAR"

echo ""
echo "Step 1: Creating mount points..."
mkdir -p "$MOUNT_PRIVATE"
mkdir -p "$MOUNT_GENERAL"
echo "  ✓ Mount points created"

echo ""
echo "Step 2: Creating credentials file..."
cat > "$CREDENTIALS_FILE" <<EOF
username=$DOMAIN_USER
password=$DOMAIN_PASS
domain=$DOMAIN
EOF

chmod 600 "$CREDENTIALS_FILE"
echo "  ✓ Credentials file created"

echo ""
echo "Step 3: Adding to /etc/fstab..."
# Remove existing entries if any
sed -i '/MORNINGSTAR-DC01\/Private/d' /etc/fstab
sed -i '/MORNINGSTAR-DC01\/General/d' /etc/fstab

# Add new entries
cat >> /etc/fstab <<EOF

# Network shares
$PRIVATE_SHARE $MOUNT_PRIVATE cifs credentials=$CREDENTIALS_FILE,uid=1000,gid=1000,iocharset=utf8,file_mode=0777,dir_mode=0777,vers=3.0 0 0
$GENERAL_SHARE $MOUNT_GENERAL cifs credentials=$CREDENTIALS_FILE,uid=1000,gid=1000,iocharset=utf8,file_mode=0777,dir_mode=0777,vers=3.0 0 0
EOF

echo "  ✓ Added to /etc/fstab"

echo ""
echo "Step 4: Mounting shares..."
mount -a

if [ $? -eq 0 ]; then
    echo "  ✓ Shares mounted successfully"
else
    echo "  ✗ Failed to mount shares"
    echo "  Check credentials and network connectivity"
    exit 1
fi

echo ""
echo "Step 5: Verifying mounts..."
if mountpoint -q "$MOUNT_PRIVATE"; then
    echo "  ✓ Private share mounted: $MOUNT_PRIVATE"
else
    echo "  ✗ Private share not mounted"
fi

if mountpoint -q "$MOUNT_GENERAL"; then
    echo "  ✓ General share mounted: $MOUNT_GENERAL"
else
    echo "  ✗ General share not mounted"
fi

echo ""
echo "========================================"
echo "✓ Network shares configured!"
echo "========================================"
echo ""
echo "Shares available at:"
echo "  - $MOUNT_PRIVATE (Private drive)"
echo "  - $MOUNT_GENERAL (General drive)"
echo ""
echo "Note: Shares will mount automatically on boot"
echo ""


