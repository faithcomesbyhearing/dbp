#!/bin/sh

# Export variables from .env to staging
set -a
source /var/app/staging/.env
set +a

# Install New Relic Agent
cd /opt
curl -Ls -o newrelic-php5.tar.gz https://download.newrelic.com/php_agent/archive/10.14.0.3/newrelic-php5-10.14.0.3-linux.tar.gz
gzip -dc newrelic-php5.tar.gz | tar xf -
cd newrelic-php5-*
export NR_INSTALL_SILENT=1 
export NR_INSTALL_KEY=$NEW_RELIC_LICENSE_KEY 
./newrelic-install install

# Clean up
cd .. && rm newrelic-php5.tar.gz

# Configure newrelic.ini
echo extension=newrelic.so | tee /etc/php.d/newrelic.ini
echo newrelic.enabled=true | tee -a /etc/php.d/newrelic.ini
echo newrelic.loglevel=debug | tee -a /etc/php.d/newrelic.ini
echo newrelic.license=\"$NEW_RELIC_LICENSE_KEY\" | tee -a /etc/php.d/newrelic.ini
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" | tee -a /etc/php.d/newrelic.ini