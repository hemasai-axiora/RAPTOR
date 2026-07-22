#!/bin/bash
BACKUP_DIR="/home/ubuntu/backups"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +"%Y-%m-%d_%H-%M-%S")
BACKUP_FILE="$BACKUP_DIR/raptor_db_$TIMESTAMP.sql.gz"

echo "Starting RAPTOR Database Backup..."
docker exec raptor-db mysqldump -u raptor_user -pRaptorProd@2026! --no-tablespaces raptor_crm_db 2>/dev/null | gzip > "$BACKUP_FILE"

if [ -s "$BACKUP_FILE" ]; then
    echo "Backup successful: $BACKUP_FILE ($(du -h "$BACKUP_FILE" | cut -f1))"
else
    echo "Backup failed!"
    exit 1
fi

# Retention Policy: Delete backups older than 30 days
find "$BACKUP_DIR" -type f -name "raptor_db_*.sql.gz" -mtime +30 -delete
