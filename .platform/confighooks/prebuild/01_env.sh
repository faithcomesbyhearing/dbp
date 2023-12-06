#!/bin/sh
#note: this is required for confighooks
cp /tmp/.env /var/app/staging/.env
cp /tmp/pub.pem /var/app/staging/pub.pem
cp /tmp/priv.pem /var/app/staging/priv.pem
cp /tmp/priv.pem /var/app/staging/storage/app/priv.pem