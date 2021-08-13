#!/bin/sh

sudo yum install libmemcached -y
sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl install -D 'enable-memcached-igbinary="yes"' memcached | true
/bin/sed -i 's/;memcached.serializer = igbinary/memcached.serializer = igbinary/g' /etc/php.d/50-memcached.ini    
    
