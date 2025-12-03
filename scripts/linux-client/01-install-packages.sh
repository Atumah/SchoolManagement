#!/bin/bash
# ============================================
# Script: Install Required Packages
# Purpose: Installs Samba, Winbind, and related packages for AD domain join
# Usage: Run as root or with sudo
# ============================================

set -e

echo "========================================"
echo "Installing Required Packages for AD Join"
echo "========================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: This script must be run as root or with sudo"
    exit 1
fi

echo "Step 1: Updating package list..."
apt update

echo ""
echo "Step 2: Installing Samba and Winbind packages..."
apt install -y \
    samba \
    winbind \
    libnss-winbind \
    libpam-winbind \
    krb5-user \
    cifs-utils \
    realmd

echo ""
echo "========================================"
echo "✓ Packages installed successfully!"
echo "========================================"
echo ""
echo "Next step: Run 02-join-domain.sh"
echo ""


