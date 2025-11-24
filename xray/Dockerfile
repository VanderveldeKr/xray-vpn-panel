FROM debian:bookworm-slim

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    curl \
    unzip \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Установка Xray
RUN bash -c "$(curl -L https://github.com/XTLS/Xray-install/raw/main/install-release.sh)" @ install --version latest

# Создание директорий
RUN mkdir -p /var/log/xray /etc/xray/certs

# Копирование конфигурации
COPY config.json /etc/xray/config.json

# Права доступа
RUN chmod 644 /etc/xray/config.json

EXPOSE 8443 8444

CMD ["/usr/local/bin/xray", "run", "-config", "/etc/xray/config.json"]

