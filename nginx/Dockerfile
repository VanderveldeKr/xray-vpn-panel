FROM nginx:alpine

# Установка зависимостей
RUN apk add --no-cache \
    certbot \
    certbot-nginx \
    openssl

# Копирование конфигурации
COPY nginx.conf /etc/nginx/nginx.conf
COPY default.conf /etc/nginx/conf.d/default.conf

# Создание директорий
RUN mkdir -p /var/www/html /var/www/certbot

EXPOSE 80 443

CMD ["nginx", "-g", "daemon off;"]

