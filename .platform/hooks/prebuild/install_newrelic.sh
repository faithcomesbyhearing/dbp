#!/bin/sh
# original, for reference
# rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
# yum install newrelic-php5 -y
# cp /usr/lib/newrelic-php5/scripts/newrelic.ini.template /etc/php.d/newrelic.ini

# source /var/app/staging/.env
# export NR_INSTALL_SILENT=true;export NR_INSTALL_KEY; newrelic-install install
# echo newrelic.enabled=true  >> /etc/php.d/newrelic.ini
# echo newrelic.loglevel=debug  >> /etc/php.d/newrelic.ini

# sed -i 's/newrelic.appname/;newrelic.appname/g' /etc/php.d/newrelic.ini
# echo newrelic.appname=\"$NEW_RELIC_APP_NAME\" >> /etc/php.d/newrelic.ini

# reference: https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-arm64/
yum update -y
yum install -y git 
yum install -y amazon-linux-extras
amazon-linux-extras install -y epel
amazon-linux-extras install -y golang1.11
yum -y groupinstall "Development Tools"
yum -y install \
   libcurl-devel \
   openssl-devel openssl-static \
   pcre-devel pcre-static \
   zlib-devel zlib-static
amazon-linux-extras install -y  php8.0
yum install -y php-devel

cd /tmp
git clone --depth 1 github.com/newrelic/newrelic-php-agent
cd newrelic-php-agent
make all
make agent-install
mkdir /var/log/newrelic
chmod 777 /var/log/newrelic
cp bin/daemon /usr/bin/newrelic-daemon
\cp agent/scripts/newrelic.ini.template /etc/php.d/newrelic.ini
sed -i 's/newrelic.appname/;newrelic.appname/g' /etc/php.d/newrelic.ini

export $(grep -v '^#' /var/app/staging/.env | xargs)
sed -i "s/REPLACE_WITH_REAL_KEY/$NR_INSTALL_KEY/g" /etc/php.d/newrelic.ini
