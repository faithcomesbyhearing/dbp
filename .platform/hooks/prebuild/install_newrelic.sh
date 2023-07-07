#!/bin/sh

# Install New Relic Agent
echo "deb http://apt.newrelic.com/debian/ newrelic non-free" | tee /etc/apt/sources.list.d/newrelic.list
wget -O - https://download.newrelic.com/548C16BF.gpg | apt-key add -
apt-get update && apt-get install -y newrelic-php5
export NR_INSTALL_SILENT=1
newrelic-install install

sed -i -e "s/REPLACE_WITH_REAL_KEY/${NEW_RELIC_LICENSE_KEY}/" \
    -e "s/newrelic.appname[[:space:]]=[[:space:]].*/newrelic.appname=\"${NEW_RELIC_APP_NAME}\"/" \
    $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
echo "newrelic.enabled=true"  >> $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
echo "newrelic.loglevel=debug"  >> $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
