FROM php:8.0.8-fpm

RUN apt-get update && \
apt-get install -y \
apt-utils \
zip \
git \
zlib1g-dev \
libzip-dev \
libicu-dev \
g++ \
libpq-dev \
libmemcached-dev

# Install intl extension
RUN docker-php-ext-configure intl \
&& docker-php-ext-install intl

# Install ext-zip
RUN docker-php-ext-install zip

# Enable opcache
RUN docker-php-ext-enable opcache

# Install pdo
RUN docker-php-ext-install pdo

# Install mysql
RUN docker-php-ext-install pdo_mysql && docker-php-ext-enable pdo_mysql

# Install memcached
RUN pecl install memcached-3.1.5 &&\
docker-php-ext-enable memcached

# Install composer
RUN curl -sS https://getcomposer.org/installer \
  | php -- --install-dir=/usr/local/bin --filename=composer

# Setup workdir
WORKDIR /opt/app

RUN chown -R www-data:www-data /opt/app

RUN mkdir /opt/app/storage

RUN mkdir /opt/app/storage/logs

RUN chown -R www-data:www-data /opt/app/storage
