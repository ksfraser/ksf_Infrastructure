# KSF Dockerfile - FrontAccounting 2.4.x on PHP 7.4
# Build: podman build -t ksf-fa:php7.4 -f docker/fa-alpine/Dockerfile docker/fa-alpine/

FROM docker.io/php:7.4-apache

LABEL maintainer="KSF Development Team"
LABEL description="FrontAccounting UAT Environment (PHP 7.4)"

# Install PHP extensions
RUN apt-get update --allow-releaseinfo-change -o Acquire::Check-Valid-Until=false && \
    apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype-dev libzip-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install mysqli gd zip && \
    rm -rf /var/lib/apt/lists/*

# Enable Apache modules
RUN a2enmod rewrite

# Configure PHP settings
RUN echo "memory_limit=256M\nupload_max_filesize=64M\npost_max_size=64M" > /usr/local/etc/php/conf.d/ksf.ini

# Copy FA application files
COPY fa/2.4.3/ /var/www/html/

EXPOSE 80

CMD ["apache2ctl", "-D", "FOREGROUND"]