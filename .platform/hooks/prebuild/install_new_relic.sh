#!/bin/sh

source /var/app/staging/.env && \
rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
yum install newrelic-php5 -y && \
export NR_INSTALL_SILENT=true && \
export NR_INSTALL_KEY && \
newrelic-install install && \
/bin/sed -i "s/PHP Application/$NEW_RELIC_APP_NAME/g" /etc/php.d/newrelic.ini    