#!/bin/sh
set -e

# 从环境变量生成 .env
if [ ! -f /var/www/.env ]; then
    echo ">>> Generating .env from environment..."
    cat > /var/www/.env << EOF
APP_ENV=${APP_ENV:-production}
APP_DEBUG=${APP_DEBUG:-false}
APP_URL=${APP_URL:-http://localhost}

DB_HOST=${DB_HOST:-db}
DB_PORT=${DB_PORT:-3306}
DB_NAME=${DB_NAME:-card_auth}
DB_USER=${DB_USER:-card_auth}
DB_PASS=${DB_PASS:-}
DB_PREFIX=${DB_PREFIX:-ca_}

JWT_SECRET=${JWT_SECRET:-}

PAYMENT_TYPE=${PAYMENT_TYPE:-epay}
PAYMENT_API_URL=${PAYMENT_API_URL:-}
PAYMENT_APP_ID=${PAYMENT_APP_ID:-}
PAYMENT_APP_KEY=${PAYMENT_APP_KEY:-}
EOF
    chmod 644 /var/www/.env
    echo ">>> .env generated"
fi

# 时区：统一北京时间
export TZ=Asia/Shanghai
if [ ! -f /usr/local/etc/php/conf.d/timezone.ini ]; then
    echo "date.timezone = Asia/Shanghai" > /usr/local/etc/php/conf.d/timezone.ini
fi

# 等待 MySQL 就绪
echo ">>> Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
until nc -z ${DB_HOST} ${DB_PORT} 2>/dev/null; do
    sleep 2
done
echo ">>> MySQL is ready"

# 确保 MySQL 会话时区为北京时间
mysql -h"${DB_HOST}" -P"${DB_PORT}" -u"${DB_USER}" -p"${DB_PASS}" -e "SET GLOBAL time_zone='+08:00';" 2>/dev/null || true

# 启动 Supervisor (Nginx + PHP-FPM)
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
