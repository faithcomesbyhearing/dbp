#!/bin/sh

# Update Composer binary.

#export COMPOSER_HOME=/root
export COMPOSER_MEMORY_LIMIT=-1

#sudo COMPOSER_MEMORY_LIMIT=-1 /usr/bin/composer.phar self-update
echo "prebuild / update_composer.sh -- command commented out"
echo "COMPOSER_MEMORY_LIMIT = " $COMPOSER_MEMORY_LIMIT
touch /tmp/touch-from-update-composer