<?php

/**
 * @file
 * Database probe for the entrypoint.
 *
 * Usage: php db.php ping|state.
 *
 * Reads MUKURTU_DB_* from the environment. The "ping" command exits 0 when the
 * database accepts the connection; otherwise it prints the reason on stderr and
 * exits 2. The "state" command prints "empty" (no tables), "installed" (Drupal's
 * users_field_data table exists), or "partial" (tables exist but Drupal is not
 * installed).
 */

$host = getenv('MUKURTU_DB_HOST');
$port = getenv('MUKURTU_DB_PORT') ?: '3306';
$name = getenv('MUKURTU_DB_NAME');
$prefix = getenv('MUKURTU_DB_PREFIX') ?: '';

try {
  $pdo = new PDO(
    "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
    getenv('MUKURTU_DB_USER'),
    getenv('MUKURTU_DB_PASSWORD'),
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 3]
  );
}
catch (PDOException $e) {
  fwrite(STDERR, $e->getMessage() . "\n");
  exit(2);
}

if (($argv[1] ?? '') === 'state') {
  $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
  if (!$tables) {
    echo 'empty';
  }
  elseif (in_array($prefix . 'users_field_data', $tables, TRUE)) {
    echo 'installed';
  }
  else {
    echo 'partial';
  }
}
