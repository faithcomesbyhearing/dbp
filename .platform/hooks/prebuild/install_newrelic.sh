#!/bin/bash -x
echo "pwd=" `pwd`
whoami

ls -la 
rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
yum install newrelic-php5 -y
source /var/app/staging/.env
NR_INSTALL_SILENT=true
export NR_INSTALL_KEY; newrelic-install install
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" >> /etc/php.d/newrelic.ini
