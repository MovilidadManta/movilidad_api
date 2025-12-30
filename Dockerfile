FROM php:8.2-fpm

# Instalar dependencias y extensiones necesarias
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    libpq-dev \
    curl \
    iputils-ping \
    net-tools \
    traceroute \
    openssl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo_pgsql opcache

# Configurar OPcache
COPY ./php.ini /usr/local/etc/php/conf.d/opcache.ini

# Crear y copiar el script para modificar /etc/hosts
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar el código fuente (excluir node_modules y vendor con .dockerignore)
COPY . /var/www/html

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Instalar dependencias PHP con Composer en modo producción
RUN composer install --no-dev --optimize-autoloader

# Establecer permisos adecuados en storage y bootstrap/cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
RUN chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Exponer el puerto 9000 para PHP-FPM
EXPOSE 9000

# Configurar el script de inicio
ENTRYPOINT ["/entrypoint.sh"]

# Iniciar PHP-FPM
CMD ["php-fpm"]