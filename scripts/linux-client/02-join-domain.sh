#!/bin/bash
# ============================================
# Script: Join Active Directory Domain
# Purpose: Joins Linux client to morningstar.local domain
# Usage: Run as root or with sudo
# ============================================

set -e

echo "========================================"
echo "Joining Active Directory Domain"
echo "========================================"
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: This script must be run as root or with sudo"
    exit 1
fi

# Configuration
DOMAIN="morningstar.local"
DOMAIN_UPPER="MORNINGSTAR.LOCAL"
ADMIN_USER="Administrator"

echo "Domain: $DOMAIN"
echo "Admin User: $ADMIN_USER"
echo ""
read -p "Enter Administrator password: " -s ADMIN_PASS
echo ""

echo ""
echo "Step 1: Configuring Samba..."
cat > /etc/samba/smb.conf <<EOF
[global]
   workgroup = MORNINGSTAR
   realm = MORNINGSTAR.LOCAL
   security = ads
   idmap uid = 10000-20000
   idmap gid = 10000-20000
   winbind enum users = yes
   winbind enum groups = yes
   winbind use default domain = yes
   template homedir = /home/%U
   template shell = /bin/bash
   winbind offline logon = yes
EOF

echo "  ✓ Samba configured"

echo ""
echo "Step 2: Stopping services..."
systemctl stop smbd nmbd winbind
systemctl disable smbd nmbd

echo ""
echo "Step 3: Joining domain..."
echo "$ADMIN_PASS" | realm join -U "$ADMIN_USER" "$DOMAIN"

if [ $? -eq 0 ]; then
    echo "  ✓ Successfully joined domain"
else
    echo "  ✗ Failed to join domain"
    exit 1
fi

echo ""
echo "Step 4: Configuring NSS (Name Service Switch)..."
# Backup original
cp /etc/nsswitch.conf /etc/nsswitch.conf.backup

# Update nsswitch.conf
sed -i 's/^passwd:.*/passwd:         compat winbind/' /etc/nsswitch.conf
sed -i 's/^group:.*/group:          compat winbind/' /etc/nsswitch.conf
sed -i 's/^shadow:.*/shadow:         compat winbind/' /etc/nsswitch.conf

echo "  ✓ NSS configured"

echo ""
echo "Step 5: Configuring PAM..."
pam-auth-update --enable mkhomedir
pam-auth-update --enable winbind

echo "  ✓ PAM configured"

echo ""
echo "Step 6: Restarting services..."
systemctl restart winbind
systemctl enable winbind

echo ""
echo "Step 7: Testing domain connection..."
if getent passwd "MORNINGSTAR\\teacher1" > /dev/null; then
    echo "  ✓ Domain connection successful"
    echo "  ✓ Test user found: MORNINGSTAR\\teacher1"
else
    echo "  ⚠ Could not find test user (may need to wait a few minutes)"
fi

echo ""
echo "========================================"
echo "✓ Domain join completed!"
echo "========================================"
echo ""
echo "Next steps:"
echo "  1. Restart the system"
echo "  2. Log in as: MORNINGSTAR\\teacher1"
echo "  3. Run 03-mount-shares.sh to mount network drives"
echo ""

