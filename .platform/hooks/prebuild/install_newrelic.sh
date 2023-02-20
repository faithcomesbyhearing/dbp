#!/bin/sh

rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
yum install newrelic-php5 -y
cp /usr/lib/newrelic-php5/scripts/newrelic.ini.template /etc/php.d/newrelic.ini

# currently, NewRelic does not provide an install package for ARM64
# An AMI was created manually based on:
# 1) the beanstalk platform AMI (ami-0e96552b04cc8cb6d)
# 2) manually installing NewRelic per these instructions: https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-arm64/
#


source /var/app/staging/.env
export NR_INSTALL_SILENT=true;export NR_INSTALL_KEY; newrelic-install install
echo newrelic.enabled=true  >> /etc/php.d/newrelic.ini
echo newrelic.loglevel=debug  >> /etc/php.d/newrelic.ini

sed -i 's/newrelic.appname/;newrelic.appname/g' /etc/php.d/newrelic.ini
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" >> /etc/php.d/newrelic.ini