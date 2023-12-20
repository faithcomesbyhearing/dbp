#!/bin/sh

sudo dnf install libzip libzip-devel -y
sudo pecl install zip
echo "extension=zip.so" | sudo tee /etc/php.d/20-zip.ini
