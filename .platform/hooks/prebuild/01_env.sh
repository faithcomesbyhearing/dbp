#!/bin/sh
ls -la /tmp

cp /tmp/.env /var/app/staging/.env
cp /tmp/pub.pem /var/app/staging/pub.pem
cp /tmp/priv.pem /var/app/staging/priv.pem

ls -la /var/app/staging