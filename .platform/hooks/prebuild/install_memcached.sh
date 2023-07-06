#!/bin/sh


sudo yum install libmemcached-devel -y
sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl install igbinary
/usr/bin/yes 'no'| /usr/bin/pecl install -D 'enable-memcached-igbinary="yes"' memcached 
    
#hack..pecl install adds the shared library extensions to to php.ini, but in the wrong order (eg igbinary is after memcached)
# so comment out the pecl config in php.ini and instead copy the ini files into php.d, which should have the same effect
sed -i 's/extension="memcached.so"/;extension="memcached.so"/g' /etc/php.ini
sed -i 's/extension="igbinary.so"/;extension="igbinary.so"/g' /etc/php.ini
cp .platform/files/etc/php.d/45-igbinary.ini /etc/php.d
cp .platform/files/etc/php.d/50-memcached.ini /etc/php.d
