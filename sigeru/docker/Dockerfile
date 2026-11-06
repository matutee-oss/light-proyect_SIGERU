FROM php:8.2-apache

# Extensiones de PHP: PDO MySQL y zip (Composer la usa para descargar paquetes)
RUN apt-get update \
 && apt-get install -y --no-install-recommends libzip-dev unzip git \
 && docker-php-ext-install pdo pdo_mysql zip \
 && rm -rf /var/lib/apt/lists/*

# Composer (para instalar PHPUnit y correr los tests dentro del contenedor)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache: mod_rewrite + .htaccess (necesario para que llegue el header Authorization)
RUN a2enmod rewrite headers
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf

# Configuración de PHP: hora de Uruguay y fotos de perfil de hasta 3 MB
RUN { \
      echo 'date.timezone = America/Montevideo'; \
      echo 'upload_max_filesize = 4M'; \
      echo 'post_max_size = 5M'; \
      echo 'display_errors = Off'; \
      echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/sigeru.ini

WORKDIR /var/www/html

COPY backend/ /var/www/html/
RUN composer install --no-interaction --no-progress \
 && mkdir -p uploads/perfiles \
 && chown -R www-data:www-data uploads

# Al arrancar se asegura que la carpeta de fotos exista y sea escribible
# (con el volumen montado desde Windows a veces llega sin permisos)
CMD ["bash", "-c", "mkdir -p uploads/perfiles && chown -R www-data:www-data uploads && apache2-foreground"]

EXPOSE 80
