#!/bin/sh

# Install PhpRedis

# Laravel uses by default PhpRedis, so the extension needs to be installed.
# https://github.com/phpredis/phpredis

# For Predis, this extension is not necessarily needed.
# Enabled by default since it's latest Laravel version's default driver.

#sudo yum -y install php-redis


# packages:
#   yum:
#     libmemcached-devel: []
# commands:
#   01_install_memcached:
#     command: /usr/bin/yes 'no'| /usr/bin/pecl install memcached
#     test: '! /usr/bin/pecl info memcached'
#   02_remove_extension:
#     command: /bin/sed -i -e '/extension="memcached.so"/d' /etc/php.ini
#   03_create_conf:
#     command: /bin/echo 'extension="memcached.so"' > /etc/php.d/41-memcached.ini

    sudo yum install libmemcached-devel -y
    sudo pecl channel-update pecl.php.net
    /usr/bin/yes 'no'| /usr/bin/pecl install memcached
    /bin/sed -i -e '/extension="memcached.so"/d' /etc/php.ini
    /bin/echo 'extension="memcached.so"' > /etc/php.d/41-memcached.ini # there is already a 50-memcached.ini. may not need this

