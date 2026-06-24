<?php
/**
 * @file settings.local.php
 *
 * Local environment overrides and settings.
 */

declare(strict_types=1);

// Suppress PHP error output so it cannot reach the response body before
// session headers are set. Errors are still written to the server error log.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Set database settings for Tugboat.
$databases['default']['default'] = [
  'database' => 'tugboat',
  'username' => 'tugboat',
  'password' => 'tugboat',
  'prefix' => '',
  'host' => 'mariadb',
  'port' => '3306',
  'namespace' => 'Drupal\\Core\\Database\\Driver\\mysql',
  'driver' => 'mysql',
];

// Use the TUGBOAT_REPO_ID to generate a hash salt for Tugboat sites.
$settings['hash_salt'] = hash('sha256', getenv('TUGBOAT_REPO_ID'));

// Set trusted hosts to disallow pointing other domains at the previews.
$settings['trusted_host_patterns'] = [
  '\.tugboatqa\.com$',
];

// Prevent Drupal from setting read-only permissions on sites/default.
$settings['skip_permissions_hardening'] = TRUE;

// Specify a private files path.
$settings['file_private_path'] = '../private_files';

// Specify a config directory.
$settings['config_sync_directory'] = 'sites/default/files/sync';
