-- KSF Database Initialization
-- This script runs on first MariaDB container start

CREATE DATABASE IF NOT EXISTS ksf_fa;
CREATE DATABASE IF NOT EXISTS ksf_wp;
CREATE DATABASE IF NOT EXISTS wordpress;

GRANT ALL PRIVILEGES ON ksf_fa.* TO 'ksf_user'@'%';
GRANT ALL PRIVILEGES ON ksf_wp.* TO 'ksf_user'@'%';
GRANT ALL PRIVILEGES ON wordpress.* TO 'ksf_user'@'%';
FLUSH PRIVILEGES;