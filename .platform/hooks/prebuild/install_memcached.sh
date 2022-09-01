#!/bin/sh

# 9/1/22 - recent platform upgrade, now encountering errors. igbinary was installed at /usr/lib64/php/modules, but cannot be found 
# cp .platform/files/etc/php.d/40-igbinary.ini /etc/php.d
# cp .platform/files/etc/php.d/50-memcached.ini /etc/php.d

sudo yum install libmemcached-devel -y
sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl install igbinary
/usr/bin/yes 'no'| /usr/bin/pecl install -D 'enable-memcached-igbinary="yes"' memcached 
    
#hack..pecl install adds this to php.ini, and I cannot figure out how to configure it differently.
#ultimately, the two extensions are loaded and configured elsewhere
# 9/1/22 - recent platform upgrade, now encountering errors. igbinary was installed at /usr/lib64/php/modules, but cannot be found 
# sed -i 's/extension="memcached.so"/;extension="memcached.so"/g' /etc/php.ini
# sed -i 's/extension="igbinary.so"/;extension="igbinary.so"/g' /etc/php.ini


# this is a check for failure, in case set -e doesn't cause failure
pecl list |grep "Unable to load"