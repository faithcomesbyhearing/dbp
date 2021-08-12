#!/bin/sh

# current working directory will have .env file from S3
source .env && \
echo "newrelic.appname: $NEW_RELIC_APP_NAME" | sudo tee -a /etc/php.d/60-newrelic.ini && \

rpm -Uvh http://yum.newrelic.com/pub/newrelic/el5/x86_64/newrelic-repo-5-3.noarch.rpm
yum install newrelic-php5 -y && \
export NR_INSTALL_SILENT=true && \
export NR_INSTALL_KEY && \
newrelic-install install

#infrastructure agent
#echo "license_key: $NR_INSTALL_KEY" | sudo tee -a /etc/newrelic-infra.yml && \
#sudo curl -o /etc/yum.repos.d/newrelic-infra.repo https://download.newrelic.com/infrastructure_agent/linux/yum/el/7/x86_64/newrelic-infra.repo && \
#sudo yum -q makecache -y --disablerepo='*' --enablerepo='newrelic-infra' && \
#sudo yum install newrelic-infra -y

