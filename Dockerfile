FROM php:8.2-apache

# 1. Instalar dependencias del sistema y extensiones de Postgres
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    libpng-dev \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Habilitar mod_rewrite de Apache (Vital para las rutas de Laravel)
RUN a2enmod rewrite

# 3. Configurar el DocumentRoot de Apache a la carpeta /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Copiar los archivos del proyecto
WORKDIR /var/www/html
COPY . .

# 5. Instalar Composer y dependencias
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# 6. Permisos para carpetas de Storage y Cache
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Exponer el puerto 80
EXPOSE 80