#!/bin/bash
sudo podman run -d \
  --name ksfii_app-fa \
  --hostname ksfii_app \
  --network ksf_network \
  -p 8080:80 \
  -e "DB_DSN=mysql:host=ksf_fa-mariadb;dbname=ksf_fa;charset=utf8" \
  -e "DB_USER=ksf_user" \
  -e "DB_PASS=ksfuser2026!" \
  -v /home/kevin/Documents/ksf_Infrastructure/FA/2.4.3:/var/www/html:ro \
  -v /home/kevin/Documents/ksf_Infrastructure/fa_modules:/var/www/html/modules:ro \
  -v /home/kevin/Documents/ksf_Infrastructure/fa/ksfii_app/config/config_db.php:/var/www/html/config_db.php:ro \
  -v /home/kevin/Documents/ksf_Infrastructure/fa/ksfii_app/installed_extensions.php:/var/www/html/installed_extensions.php:ro \
  -v /home/kevin/Documents/ksf_Infrastructure/fa/ksfii_app/company/0:/var/www/html/company/0:ro \
  localhost/ksf-fa:php7.4
