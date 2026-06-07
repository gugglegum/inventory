FROM nginx:latest

# Синхронизация UID/GID с хостом (www-data = 33:33)
RUN groupmod -g 33 www-data && usermod -u 33 www-data

# Настройка Nginx на использование www-data
RUN sed -i 's/^user[[:space:]]\+nginx;/user www-data;/' /etc/nginx/nginx.conf
