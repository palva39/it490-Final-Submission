#!/bin/bash

WEB_ROOT="/var/www/sample"
BACKUP_DIR="/backups"

echo " lets roll that back...$(hostname)"

# get the newest backup file
BACKUP_FILE=$(ls -t ${BACKUP_DIR}/sample_*.tar.gz 2>/dev/null | head -n 1)

if [ -z "$BACKUP_FILE" ]; then
  echo "No backups found in ${BACKUP_DIR}"
  exit 1
fi

echo "Restoring from backup: $BACKUP_FILE"
tar -xzf "$BACKUP_FILE" -C /

echo "Restarting Apache..."
sudo systemctl restart apache2

echo "Rollback complete yay. Restored from: $BACKUP_FILE"
exit 0