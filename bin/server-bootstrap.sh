#!/usr/bin/env bash
#
# Prepare a fresh Ubuntu 22.04/24.04 server (x86_64 or arm64, e.g. an Oracle Cloud Always Free
# Ampere A1 instance) to run Luminara CRM with compose.prod.yaml.
#
#   sudo bash bin/server-bootstrap.sh
#
# Safe to run again. It installs Docker Engine and the Compose plugin from Docker's own apt
# repository, turns on automatic security updates (with a 03:30 reboot when one needs it; the
# containers restart on their own), adds swap if there is none, and creates /opt/luminara for
# the stack. It opens no inbound ports: the Cloudflare tunnel connects outwards.

set -euo pipefail

if [[ ${EUID} -ne 0 ]]; then
    echo "Run as root: sudo bash $0" >&2
    exit 1
fi

. /etc/os-release

if [[ ${ID} != "ubuntu" ]]; then
    echo "This script supports Ubuntu only (found ${ID})." >&2
    exit 1
fi

owner="${SUDO_USER:-ubuntu}"
export DEBIAN_FRONTEND=noninteractive

echo "==> System packages and security updates"
apt-get update -q
apt-get upgrade -yq
apt-get install -yq --no-install-recommends ca-certificates curl gnupg unattended-upgrades

cat > /etc/apt/apt.conf.d/52luminara-unattended <<'EOF'
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "03:30";
EOF
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
EOF

echo "==> Docker Engine and Compose plugin"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi

systemctl enable --now docker
usermod -aG docker "${owner}"

echo "==> Swap"
if [[ -z "$(swapon --show --noheadings)" ]]; then
    fallocate -l 4G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile >/dev/null
    swapon /swapfile
    grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

echo "==> Stack folder"
install -d -o "${owner}" -g "${owner}" -m 0750 /opt/luminara

echo
echo "Done. Log out and back in so ${owner} can use docker without sudo, then follow"
echo "docs/deployment-oracle-free-tier.md from step 3."
docker --version
docker compose version
