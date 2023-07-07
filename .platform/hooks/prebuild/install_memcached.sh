#!/bin/sh


# Install igbinary and memcached
apt-get update
apt-get install -y php-pear php-dev
apt-get install -y libmemcached-dev
pecl install igbinary
pecl install -D 'enable-memcached-igbinary="yes"' memcached
echo "extension=\"igbinary.so\""  > $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/45-igbinary.ini
echo "extension=memcached.so"  > $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/50-memcached.ini
