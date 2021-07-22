#!/bin/sh


echo "bbbbbbbbbbbbplatform/hooks/predeploy/echo.sh"

touch /var/app/staging/foo.txt
echo "APP_SERVER_NAME=$(curl http://169.254.169.254/latest/meta-data/instance-id)" >> /var/app/staging/foo.txt
echo "API_URL=$(/opt/elasticbeanstalk/bin/get-config environment -k API_URL)" >> /var/app/staging/foo.txt


echo "APP_SERVER_NAME=$(curl http://169.254.169.254/latest/meta-data/instance-id)" >> /var/app/staging/.env
echo "API_URL=$(/opt/elasticbeanstalk/bin/get-config environment -k API_URL)" >> /var/app/staging/.env

