-- Create the test database
CREATE DATABASE IF NOT EXISTS `db_test`;

-- Ensure the 'cortex' user has permissions for both
GRANT ALL PRIVILEGES ON `db_test`.* TO 'cortex'@'%';