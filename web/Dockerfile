FROM php:8.3-fpm-alpine

# Установка зависимостей
RUN apk add --no-cache \
    bash \
    curl \
    python3 \
    py3-pip \
    py3-qrcode \
    py3-pillow \
    sudo \
    jq

# Установка PHP расширений
RUN docker-php-ext-install pdo pdo_mysql

# Копирование конфигурации PHP
COPY php.ini /usr/local/etc/php/conf.d/custom.ini

# Создание директорий
RUN mkdir -p /var/www/html/admin /var/vpn/users

# Настройка sudo для www-data
RUN echo "www-data ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]

