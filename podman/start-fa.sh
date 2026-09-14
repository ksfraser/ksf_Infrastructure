#!/bin/bash
podman run -d \
  --name ksf-fa \
  --hostname ksf-fa \
  --network ksf_network \
  -p 8080:80 \
  -e DB_DSN="mysql:host=ksf-mariadb;dbname=ksf_fa;charset=utf8" \
  -e DB_USER=ksf_user \
  -e DB_PASS=ksfuser2024! \
  -v ksf_infrastructure_fa_data:/var/www/html \
  -v /home/kevin/Documents/ksf_Infrastructure/fa_modules:/var/www/html:Z \
  localhost/ksf-fa:latest
