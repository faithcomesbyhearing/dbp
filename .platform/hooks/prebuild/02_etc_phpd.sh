#!/bin/sh

STAGING=`/opt/elasticbeanstalk/bin/get-config platformconfig -k AppStagingDir`
cp $STAGING/.platform/files/etc/php.d/* /etc/php.d