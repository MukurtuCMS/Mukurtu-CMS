<?php

/**
 * @file
 * Database probe for the entrypoint.
 *
 * Usage: php db.php ping|state|missing.
 *
 * Reads MUKURTU_DB_* from the environment. The "ping" command exits 0 when the
 * database accepts the connection; otherwise it prints the reason on stderr and
 * exits 2. The "state" command prints "empty" (no tables), "installed" (Drupal's
 * users_field_data table exists), or "partial" (tables exist but Drupal is not
 * installed). The "missing" command prints one line, "module NAME" or "theme
 * NAME", for each enabled extension that has no NAME.info.yml under the web
 * root, and nothing when all are present. It reads the database directly, not
 * through Drupal, because Drupal can't boot cleanly while code is missing.
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

if (($argv[1] ?? '') === 'missing') {
  $query = $pdo->prepare("SELECT data FROM {$prefix}config WHERE collection = '' AND name = 'core.extension'");
  $query->execute();
  $extensions = unserialize((string) $query->fetchColumn(), ['allowed_classes' => FALSE]);
  if (!is_array($extensions)) {
    fwrite(STDERR, "core.extension could not be read from the config table\n");
    exit(2);
  }

  // Every NAME.info.yml under the web root, wherever Drupal would look for it.
  // Folders Drupal's own discovery skips are skipped here too.
  $found = [];
  $root = '/var/www/html/web';
  $skip = ['node_modules', 'bower_components', 'vendor', 'files'];
  $directories = new RecursiveCallbackFilterIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS | FilesystemIterator::FOLLOW_SYMLINKS),
    static fn(SplFileInfo $file) => $file->isDir()
      ? $file->getFilename()[0] !== '.' && !in_array($file->getFilename(), $skip, TRUE)
      : str_ends_with($file->getFilename(), '.info.yml'),
  );
  foreach (new RecursiveIteratorIterator($directories) as $file) {
    $found[substr($file->getFilename(), 0, -strlen('.info.yml'))] = TRUE;
  }

  foreach (['module', 'theme'] as $type) {
    foreach (array_keys($extensions[$type] ?? []) as $name) {
      if (!isset($found[$name])) {
        echo "$type $name\n";
      }
    }
  }
}
