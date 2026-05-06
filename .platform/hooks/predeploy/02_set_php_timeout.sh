#!/bin/bash
set -e

PHP_FPM_POOL="/etc/php-fpm.d/www.conf"

grep -q "^request_terminate_timeout" "$PHP_FPM_POOL" \
  && sed -i "s|^request_terminate_timeout.*|request_terminate_timeout = 1200s|" "$PHP_FPM_POOL" \
  || echo "request_terminate_timeout = 1200s" >> "$PHP_FPM_POOL"

grep -q "^php_admin_value\[max_execution_time\]" "$PHP_FPM_POOL" \
  && sed -i "s|^php_admin_value\[max_execution_time\].*|php_admin_value[max_execution_time] = 1200|" "$PHP_FPM_POOL" \
  || echo "php_admin_value[max_execution_time] = 1200" >> "$PHP_FPM_POOL"

systemctl restart php-fpm

