#!/bin/bash

# Скрипт установки VPN сервера в Docker
# Автор: AI Assistant
# Дата: 2025-11-24

set -e

echo "================================"
echo "VPN Server Docker Installation"
echo "================================"
echo ""

# Проверка root прав
if [ "$EUID" -ne 0 ]; then 
    echo "❌ Пожалуйста, запустите скрипт с правами root (sudo)"
    exit 1
fi

# Проверка Docker
if ! command -v docker &> /dev/null; then
    echo "📦 Docker не установлен. Устанавливаю..."
    curl -fsSL https://get.docker.com | sh
    systemctl start docker
    systemctl enable docker
    echo "✅ Docker установлен"
else
    echo "✅ Docker уже установлен"
fi

# Проверка Docker Compose
if ! command -v docker-compose &> /dev/null; then
    echo "📦 Docker Compose не установлен. Устанавливаю..."
    curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
    echo "✅ Docker Compose установлен"
else
    echo "✅ Docker Compose уже установлен"
fi

echo ""
echo "📝 Настройка конфигурации..."

# Запрос домена
read -p "Введите ваш домен (например: vpn.example.com): " DOMAIN
if [ -z "$DOMAIN" ]; then
    echo "❌ Домен обязателен!"
    exit 1
fi

# Запрос email
read -p "Введите email для Let's Encrypt: " EMAIL
if [ -z "$EMAIL" ]; then
    echo "❌ Email обязателен!"
    exit 1
fi

# Запрос пароля админки
read -sp "Введите пароль для админ-панели: " ADMIN_PASSWORD
echo ""
if [ -z "$ADMIN_PASSWORD" ]; then
    echo "❌ Пароль обязателен!"
    exit 1
fi

# Создание директорий
echo "📁 Создание директорий..."
mkdir -p data/{xray,nginx,certs,logs/{xray,nginx},users,certbot-webroot}

# Создание файла окружения
cat > .env << EOF
DOMAIN=$DOMAIN
EMAIL=$EMAIL
ADMIN_PASSWORD=$ADMIN_PASSWORD
TZ=Europe/Moscow
EOF

echo "✅ Конфигурация сохранена в .env"

# Обновление конфигурации Nginx
echo "🔧 Настройка Nginx..."
sed -i "s/DOMAIN/$DOMAIN/g" nginx/default.conf

# Создание начального файла пользователей
touch data/users/users.txt
chmod 666 data/users/users.txt

# Обновление пароля в админке
sed -i "s/vpnadmin2024/$ADMIN_PASSWORD/g" web/admin/index.php
sed -i "s/vpnadmin2024/$ADMIN_PASSWORD/g" web/admin/api.php

echo ""
echo "🚀 Запуск контейнеров..."
docker-compose up -d

echo ""
echo "⏳ Ожидание запуска сервисов..."
sleep 10

echo ""
echo "🔐 Получение SSL сертификата..."
docker-compose run --rm certbot certonly \
    --webroot \
    --webroot-path=/var/www/certbot \
    --email $EMAIL \
    --agree-tos \
    --no-eff-email \
    -d $DOMAIN

# Копирование сертификатов для Xray
echo "📋 Настройка сертификатов для Xray..."
docker cp vpn-certbot:/etc/letsencrypt/live/$DOMAIN/fullchain.pem data/xray/certs/fullchain.pem
docker cp vpn-certbot:/etc/letsencrypt/live/$DOMAIN/privkey.pem data/xray/certs/privkey.pem
chmod 644 data/xray/certs/*.pem

# Перезапуск контейнеров
echo "🔄 Перезапуск контейнеров..."
docker-compose restart

echo ""
echo "================================"
echo "✅ Установка завершена!"
echo "================================"
echo ""
echo "📊 Информация о сервере:"
echo "  Домен: https://$DOMAIN"
echo "  Админ-панель: https://$DOMAIN/admin"
echo "  Пароль админки: $ADMIN_PASSWORD"
echo ""
echo "🔧 Полезные команды:"
echo "  Просмотр логов: docker-compose logs -f"
echo "  Остановка: docker-compose stop"
echo "  Запуск: docker-compose start"
echo "  Перезапуск: docker-compose restart"
echo "  Удаление: docker-compose down"
echo ""
echo "📚 Документация: ./DOCKER-INSTALL.md"
echo ""

