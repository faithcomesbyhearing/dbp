#!/bin/sh
set -x
cp .platform/files/etc-phpd/40-igbinary.ini /etc/php.d
cp .platform/files/etc-phpd/50-memcached.ini /etc/php.d




sudo yum install libmemcached-devel -y
sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl install -D 'enable-memcached-igbinary="yes"' memcached 
/usr/bin/yes 'no'| /usr/bin/pecl install igbinary
    
