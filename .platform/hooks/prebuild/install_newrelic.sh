#!/bin/sh

rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
yum install newrelic-php5 -y
cp /usr/lib/newrelic-php5/scripts/newrelic.ini.template /etc/php.d/newrelic.ini

source /var/app/staging/.env
export NR_INSTALL_SILENT=true;export NR_INSTALL_KEY; newrelic-install install
echo newrelic.enabled=true  >> /etc/php.d/newrelic.ini
echo newrelic.loglevel=debug  >> /etc/php.d/newrelic.ini

sed -i 's/newrelic.appname/;newrelic.appname/g' /etc/php.d/newrelic.ini
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" >> /etc/php.d/newrelic.ini

# # Install New Relic Agent
# echo "deb http://apt.newrelic.com/debian/ newrelic non-free" | tee /etc/apt/sources.list.d/newrelic.list
# wget -O - https://download.newrelic.com/548C16BF.gpg | apt-key add -
# apt-get update && apt-get install -y newrelic-php5
# export NR_INSTALL_SILENT=1
# newrelic-install install

# sed -i -e "s/REPLACE_WITH_REAL_KEY/${NEW_RELIC_LICENSE_KEY}/" \
#     -e "s/newrelic.appname[[:space:]]=[[:space:]].*/newrelic.appname=\"${NEW_RELIC_APP_NAME}\"/" \
#     $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
# echo "newrelic.enabled=true"  >> $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
# echo "newrelic.loglevel=debug"  >> $(php -r "echo(PHP_CONFIG_FILE_SCAN_DIR);")/newrelic.ini
