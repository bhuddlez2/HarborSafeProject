-- Local MariaDB setup for Linux (Fedora and similar).
--
-- On these installs MariaDB authenticates root by unix_socket, so the
-- passwordless `root` in .env fails over TCP with "1698 Access denied".
-- This creates the two databases and a dedicated dev user instead.
--
-- 1. Replace both CHANGE_ME values with a password of your choosing.
-- 2. Run:  sudo mariadb < docs/local-db-setup.sql
-- 3. In portal/backend/.env set DB_USERNAME_PORTAL / DB_PASSWORD_PORTAL and
--    DB_USERNAME_FEEDBACK / DB_PASSWORD_FEEDBACK to harborsafe_dev and that
--    password. Leave the default DB_* (mariadb) connection alone.
-- 4. From portal/backend: php artisan migrate --database=Portal
--
-- The restricted FeedbackPublic user is NOT created here: its table-level
-- GRANTs need the tables to exist, so run the GRANT block from
-- Schema_Reference.md after migrating.
--
-- Database names match .env.example; charset/collation match
-- config/database.php. Don't commit a copy with a real password in it.

CREATE DATABASE IF NOT EXISTS portal   CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS feedback CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Both hosts: PHP connects over TCP to 127.0.0.1, the mariadb CLI over the socket.
CREATE USER IF NOT EXISTS 'harborsafe_dev'@'localhost' IDENTIFIED BY 'CHANGE_ME';
CREATE USER IF NOT EXISTS 'harborsafe_dev'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME';

GRANT ALL PRIVILEGES ON portal.*   TO 'harborsafe_dev'@'localhost', 'harborsafe_dev'@'127.0.0.1';
GRANT ALL PRIVILEGES ON feedback.* TO 'harborsafe_dev'@'localhost', 'harborsafe_dev'@'127.0.0.1';

FLUSH PRIVILEGES;
