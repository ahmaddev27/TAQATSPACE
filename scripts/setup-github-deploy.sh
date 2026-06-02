#!/usr/bin/env bash
#
# Sets the GitHub Actions Secrets + Variables used by the deploy workflows.
# Prereqs: `gh` installed and `gh auth login` done. Run from the repo root.
# The SSH private key is read from the generated file — never hardcoded.
#
set -euo pipefail

KEY_FILE="deploy/taqat_deploy_key"
[ -f "$KEY_FILE" ] || { echo "Missing $KEY_FILE — run: ssh-keygen -t ed25519 -f $KEY_FILE -N '' -C github-actions-deploy@taqat.space"; exit 1; }

echo "== Secrets (connection) =="
gh secret set SSH_HOST --body "68.178.169.85"
read -rp "cPanel SSH username (main account): " SSH_USER && gh secret set SSH_USER --body "$SSH_USER"
read -rp "cPanel SSH port [22]: " SSH_PORT && gh secret set SSH_PORT --body "${SSH_PORT:-22}"
gh secret set SSH_PRIVATE_KEY < "$KEY_FILE"

echo "== Variables (paths + API URLs) =="
read -rp "PROD_API_PATH    (e.g. /home/USER/api.taqat.space): " V && gh variable set PROD_API_PATH --body "$V"
read -rp "PROD_WEB_PATH    (e.g. /home/USER/taqat.space): " V && gh variable set PROD_WEB_PATH --body "$V"
gh variable set PROD_API_URL --body "https://api.taqat.space/api"
read -rp "STAGING_API_PATH (e.g. /home/USER/api.staging.taqat.space): " V && gh variable set STAGING_API_PATH --body "$V"
read -rp "STAGING_WEB_PATH (e.g. /home/USER/staging.taqat.space): " V && gh variable set STAGING_WEB_PATH --body "$V"
gh variable set STAGING_API_URL --body "https://api.staging.taqat.space/api"

echo
echo "Done. Next: add deploy/taqat_deploy_key.pub to cPanel → SSH Access → Manage SSH Keys (Import + Authorize)."
echo "Public key:"; cat "${KEY_FILE}.pub"
