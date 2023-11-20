#!/bin/sh

sudo yum groupinstall "Development Tools" -y

# Install libmemcached dependencies
sudo yum install libevent-devel cyrus-sasl-devel -y

cd /tmp

# Download the libmemcached source code (you'll need to find the current source URL)
wget https://launchpad.net/libmemcached/1.0/1.0.18/+download/libmemcached-1.0.18.tar.gz

# Extract the source code
tar -zxvf libmemcached-1.0.18.tar.gz

# Change to the directory
cd libmemcached-1.0.18

# Configure the package (you may need to add or adjust configuration flags)
./configure CXXFLAGS="-fpermissive"

# Compile and install
make && sudo make install

sudo pecl channel-update pecl.php.net
/usr/bin/yes 'no'| /usr/bin/pecl install igbinary
/usr/bin/yes 'no'| /usr/bin/pecl install -D 'enable-memcached-igbinary="yes" with-libmemcached-dir="/usr/local"' memcached 
    
#hack..pecl install adds the shared library extensions to to php.ini, but in the wrong order (eg igbinary is after memcached)
# so comment out the pecl config in php.ini and instead copy the ini files into php.d, which should have the same effect
sed -i 's/extension="memcached.so"/;extension="memcached.so"/g' /etc/php.ini
sed -i 's/extension="igbinary.so"/;extension="igbinary.so"/g' /etc/php.ini

echo "extension=igbinary.so" | sudo tee /etc/php.d/45-igbinary.ini
echo "extension=memcached.so" | sudo tee /etc/php.d/50-memcached.ini



# Install igbinary and memcached
# apt-get update
# apt-get install -y php-pear php-dev
# apt-get install -y libmemcached-dev
# pecl install igbinary
# pecl install -D 'enable-memcached-igbinary="yes"' memcached
# echo "extension=\"igbinary.so\""  > $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/45-igbinary.ini
# echo "extension=memcached.so"  > $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/50-memcached.ini

