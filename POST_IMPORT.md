# ksf_Infrastructure post_import branch

This branch contains infrastructure setup for PHP 7.4 FA image used with ksf_FA_ImportStagingProcessing.

## Docker Image
- Location: docker/fa-alpine/Dockerfile
- Base: php:7.4-apache
- Build command: podman build -t ksf-fa:php7.4 -f docker/fa-alpine/Dockerfile docker/fa-alpine/

## Container Deployment
Run the container with:
podman run -d \
  --name ksf-fa \
  --hostname ksf-fa \
  --network ksf_network \
  -p 8092:80 \
  -e MARIADB_ROOT_PASSWORD=ksfroot2024! \
  -e MARIADB_DATABASE=ksf_fa \
  -e MARIADB_USER=ksf_user \
  -e MARIADB_PASSWORD=ksfuser2024! \
  -v ksf_infrastructure_fa_data:/var/www/html \
  -v ../fa_modules:/var/www/html/modules:z \
  localhost/ksf-fa:php7.4

## Notes
- The module code is located in the bind mount at:
  /home/export_woocommerce/Documents/ksf_Infrastructure/fa_modules/ksf_FA_ImportStagingProcessing/
- Which maps to /var/www/html/modules/ksf_FA_ImportStagingProcessing/ inside the container
- All 115 module tests pass (306 assertions)
