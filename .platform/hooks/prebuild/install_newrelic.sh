#!/bin/sh

# Export variables from .env
set -a
source /var/app/staging/.env
set +a

# Install New Relic Agent
curl -Ls -o newrelic-php5.tar.gz https://download.newrelic.com/php_agent/archive/10.14.0.3/newrelic-php5-10.14.0.3-linux.tar.gz
gzip -dc newrelic-php5.tar.gz | tar xf -
cd newrelic-php5-*
env NR_INSTALL_SILENT=1 ./newrelic-install install
# Clean up
rm -rf newrelic-php5-*
rm newrelic-php5.tar.gz

# Configure newrelic.ini
echo extension=newrelic.so | tee /etc/php.d/newrelic.ini
echo newrelic.enabled=true | tee -a /etc/php.d/newrelic.ini
echo newrelic.loglevel=debug | tee -a /etc/php.d/newrelic.ini
echo newrelic.license=\"$NEW_RELIC_LICENSE_KEY\" | tee -a /etc/php.d/newrelic.ini
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" | tee -a /etc/php.d/newrelic.ini