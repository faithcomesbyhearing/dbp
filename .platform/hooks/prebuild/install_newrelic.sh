#!/bin/sh

# Export variables from .env
set -a
source /var/app/staging/.env
set +a

# Install New Relic Agent to /opt directory
cd /opt
rm -rf newrelic-php5*
FILENAME=$(curl -s 'https://download.newrelic.com/php_agent/release/' | grep -o 'newrelic-php5.*\.gz' | sed -e 's/^.*>//;s/<[^>]*>//g' | sed -n '1,$p' | grep 'linux.tar.gz$');
curl -Ls -o newrelic-php5.tar.gz https://download.newrelic.com/php_agent/release/$FILENAME;
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
echo newrelic.loglevel=info | tee -a /etc/php.d/newrelic.ini
echo newrelic.license=\"$NEW_RELIC_LICENSE_KEY\" | tee -a /etc/php.d/newrelic.ini
echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" | tee -a /etc/php.d/newrelic.ini