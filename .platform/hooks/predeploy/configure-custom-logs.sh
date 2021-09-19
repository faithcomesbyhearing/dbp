#!/bin/sh

# custom logs to map API key to Cloudwatch Signature
mkdir -p /var/log/cloudfront-api-key/rotated
#cron
cp .platform/files/etc/cron.hourly/cron.logrotate.elasticbeanstalk.cloudfront-api-key.conf /etc/cron.hourly
#logrotate
cp .platform/files/etc/logrotate.elasticbeanstalk.hourly/logrotate.elasticbeanstalk.cloudfront-api-key.conf /etc/logrotate.elasticbeanstalk.hourly
#publish to s3
cp .platform/files/opt/elasticbeanstalk/tasks/publishlogs.d/cloudfront-api-key.conf /opt/elasticbeanstalk/tasks/publishlogs.d 