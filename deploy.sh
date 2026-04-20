#!/bin/bash
echo "🚀 Starting Deployment to Hostinger..."

# Use rsync to securely and efficiently sync files.
# It only uploads files that have changed, making it incredibly fast.
rsync -avzP \
    --exclude='.git' \
    --exclude='.DS_Store' \
    --exclude='deploy.sh' \
    --exclude='*.sql' \
    -e "ssh -p 65002" \
    ./ u234315390@82.25.83.82:domains/jordanmwinukatz.com/public_html/

echo ""
echo "✅ Deployment Complete! Your live website has been updated."
