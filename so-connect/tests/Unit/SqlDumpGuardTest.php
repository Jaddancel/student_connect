<?php

use App\Support\SqlDumpGuard;

function sqlDumpFile(string $sql): string
{
    $path = tempnam(sys_get_temp_dir(), 'guard_');
    file_put_contents($path, $sql);

    return $path;
}

function guardAccepts(string $sql): bool
{
    $path = sqlDumpFile($sql);

    try {
        SqlDumpGuard::assertSafe($path);

        return true;
    } catch (RuntimeException) {
        return false;
    } finally {
        @unlink($path);
    }
}

it('accepts typical mysqldump output', function () {
    $dump = <<<'SQL'
-- MySQL dump 10.13  Distrib 8.0.46, for Linux (x86_64)
--
-- Host: mysql    Database: laravel
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40103 SET TIME_ZONE='+00:00' */;
/* plain comment with \! inside */
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `bio` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB;
LOCK TABLES `users` WRITE;
INSERT INTO `users` VALUES (1,'it\'s \\! fine; system x'),(2,NULL),(3,'multi
line
source /etc/passwd'),(4,"dq \" \! ok"),(5,'O''Brien \! x');
UNLOCK TABLES;
DELIMITER ;;
/*!50003 CREATE TRIGGER t BEFORE INSERT ON users FOR EACH ROW BEGIN SET NEW.bio = 'a;b'; END */;;
DELIMITER ;
SQL;

    expect(guardAccepts($dump))->toBeTrue();
});

it('accepts the MariaDB sandbox-mode header', function () {
    expect(guardAccepts("/*M!999999\\- enable the sandbox mode */\nSELECT 1;\n"))->toBeTrue();
});

it('rejects mysql client commands', function (string $sql) {
    expect(guardAccepts($sql))->toBeFalse();
})->with([
    'shell escape' => ["SELECT 1;\n\\! id\n"],
    'shell escape mid-line' => ["SELECT 1; \\! id\n"],
    'shell escape in versioned comment' => ["/*!50000 \\! id */;\n"],
    'system command' => ["SELECT 1;\nsystem id\n"],
    'system command, uppercase' => ["SELECT 1;\n  SYSTEM id;\n"],
    'source' => ["source /etc/passwd\n"],
    'tee' => ["tee /var/www/html/public/x.php\n"],
    'pager' => ["pager sh -c id\n"],
    'connect' => ["connect other-host\n"],
    'short tee after string closes' => ["INSERT INTO t VALUES ('a'); \\T /tmp/x\n"],
    'command after custom delimiter' => ["DELIMITER $$\nSELECT 1$$\nsystem id\n"],
    'backslash in delimiter' => ["DELIMITER \\!\n"],
]);
