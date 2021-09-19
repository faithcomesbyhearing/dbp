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