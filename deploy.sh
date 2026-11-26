#!/usr/bin/env bash
set -e

echo "🚀 Starting deployment..."

# 1. Pull the latest code
echo "📥 Pulling latest git changes..."
git pull origin main

# 2. Install PHP dependencies
echo "📦 Installing Composer dependencies..."
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction

# 3. Install frontend dependencies & compile production assets
echo "🎨 Building frontend assets..."
npm ci || npm install
npm run build

# 4. Database migrations & Provider Reconciliation Sync
echo "🗄️ Running database migrations..."
php artisan migrate --force

echo "🔄 Synchronizing Pixelforge provider usage records..."
php artisan pixelforge:sync-usage || true

# 5. Clear and rebuild caches for maximum performance
echo "⚡ Optimizing route, config, and view caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 6. Ensure storage permissions and symlinks
echo "🔗 Verifying storage symlink and permissions..."
php artisan storage:link || true
chmod -R 775 storage bootstrap/cache || true

# 7. Restart background queue worker
echo "🔄 Restarting background queue workers..."
php artisan queue:restart || true

echo "✅ Deployment finished successfully!"
