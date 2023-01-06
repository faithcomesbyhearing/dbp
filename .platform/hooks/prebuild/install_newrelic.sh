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

# attempt to use amd architecture, and newrelic package not in yum repo. So had to build, which wasn't working reliably
# reference: https://docs.newrelic.com/docs/apm/agents/php-agent/installation/php-agent-installation-arm64/
# yum update -y
# yum install -y git 
# yum install -y amazon-linux-extras
# amazon-linux-extras install -y epel
# amazon-linux-extras install -y golang1.11
# yum -y groupinstall "Development Tools"
# yum -y install \
#    libcurl-devel \
#    openssl-devel openssl-static \
#    pcre-devel pcre-static \
#    zlib-devel zlib-static
# amazon-linux-extras install -y  php8.0
# yum install -y php-devel

# cd /tmp
# git clone --depth 1 https://github.com/newrelic/newrelic-php-agent
# cd newrelic-php-agent
# make all
# make agent-install
# mkdir -p /var/log/newrelic 
# chmod 777 /var/log/newrelic
# cp bin/daemon /usr/bin/newrelic-daemon
# \cp agent/scripts/newrelic.ini.template /etc/php.d/newrelic.ini
# sed -i 's/newrelic.appname/;newrelic.appname/g' /etc/php.d/newrelic.ini

# export $(grep -v '^#' /var/app/staging/.env | xargs)
# sed -i "s/REPLACE_WITH_REAL_KEY/$NR_INSTALL_KEY/g" /etc/php.d/newrelic.ini
