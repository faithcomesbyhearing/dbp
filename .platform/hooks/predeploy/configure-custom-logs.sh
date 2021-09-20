#!/bin/sh

# custom logs to map API key to Cloudwatch Signature
#cron
cp .platform/files/etc/cron.hourly/cron.logrotate.elasticbeanstalk.cloudfront-api-key.conf /etc/cron.hourly
#logrotate to /var/log/rotated
cp .platform/files/etc/logrotate.elasticbeanstalk.hourly/logrotate.elasticbeanstalk.cloudfront-api-key.conf /etc/logrotate.elasticbeanstalk.hourly
#publish to s3 is done via existing publishlogs config, which publishes everything from /var/log/rotated
