#!/bin/sh

sudo yum install libmemcached-devel -y
sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl upgrade igbinary
/usr/bin/yes 'no'| /usr/bin/pecl upgrade -D 'enable-memcached-igbinary="yes"' memcached 
    
