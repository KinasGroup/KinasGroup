#!/bin/bash
# Setup cron job to update featured listings daily

# Get the current directory
DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Add to crontab
echo "Setting up cron job to update featured listings daily at midnight..."

# Check if crontab exists
(crontab -l 2>/dev/null | grep -v "update-featured.php"; echo "0 0 * * * php $DIR/update-featured.php >> $DIR/logs/featured-update.log 2>&1") | crontab -

echo "✅ Cron job added!"
echo "📝 Logs will be written to: $DIR/logs/featured-update.log"
