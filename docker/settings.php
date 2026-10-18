<?php

/**
 * @file
 * Drupal settings for the Mukurtu CMS container image.
 *
 * Everything site-specific is read from MUKURTU_* environment variables, so the
 * image has no per-site files to edit. Every variable is documented in
 * docs/docker.md. A secret may instead be given as a file, through the same
 * name with a _FILE suffix (for Docker or Kubernetes secrets).
 *
 * This file is baked into the image and read-only to the runtime user.
 */

declare(strict_types=1);

if (!function_exists('mukurtu_env')) {

  /**
   * Reads a setting from the environment.
   *
   * The ${NAME}_FILE form wins when both are set, so a mounted secret overrides
   * a leftover plain variable. An empty value counts as unset. Trailing newlines
   * are dropped from file contents, as editors and `echo` add them.
   */
  function mukurtu_env(string $name, ?string $default = NULL): ?string {
    $file = getenv($name . '_FILE');
    if ($file !== FALSE && $file !== '') {
      $contents = @file_get_contents($file);
      if ($contents === FALSE) {
        throw new RuntimeException("{$name}_FILE is '{$file}', which is not readable");
      }
      return rtrim($contents, "\r\n");
    }
    $value = getenv($name);
    return ($value === FALSE || $value === '') ? $default : $value;
  }

  /**
   * Reads a setting that must be present.
   */
  function mukurtu_env_required(string $name): string {
    $value = mukurtu_env($name);
    if ($value === NULL) {
      throw new RuntimeException("{$name} is required");
    }
    return $value;
  }

  /**
   * Splits a comma-separated list, trimming whitespace and dropping blanks.
   *
   * @return string[]
   */
  function mukurtu_env_list(string $value): array {
    return array_values(array_filter(array_map('trim', explode(',', $value)), 'strlen'));
  }

  /**
   * Builds Drupal trusted_host_patterns from a list of host names.
   *
   * Each host is matched exactly, with its dots literal, so example.com does
   * not trust exampleXcom or example.com.evil.org. An entry of the form
   * *.example.com trusts any subdomain. Drupal matches the patterns without
   * regard to case.
   *
   * @param string[] $hosts
   *
   * @return string[]
   */
  function mukurtu_trusted_host_patterns(array $hosts): array {
    $patterns = [];
    foreach ($hosts as $host) {
      if (str_starts_with($host, '*.')) {
        $patterns[] = '^([^.]+\.)+' . preg_quote(substr($host, 2)) . '$';
      }
      else {
        $patterns[] = '^' . preg_quote($host) . '$';
      }
    }
    return $patterns;
  }

}

$databases['default']['default'] = [
  'driver' => 'mysql',
  'namespace' => 'Drupal\\mysql\\Driver\\Database\\mysql',
  'autoload' => 'core/modules/mysql/src/Driver/Database/mysql/',
  'host' => mukurtu_env_required('MUKURTU_DB_HOST'),
  'port' => mukurtu_env('MUKURTU_DB_PORT', '3306'),
  'database' => mukurtu_env_required('MUKURTU_DB_NAME'),
  'username' => mukurtu_env_required('MUKURTU_DB_USER'),
  'password' => mukurtu_env_required('MUKURTU_DB_PASSWORD'),
  'prefix' => mukurtu_env('MUKURTU_DB_PREFIX', ''),
  'charset' => 'utf8mb4',
  'collation' => 'utf8mb4_general_ci',
];

// The salt signs one-time login links and image-style tokens. Set it explicitly
// for production. Without it, the entrypoint generates one once and keeps it in
// the private volume.
$salt = mukurtu_env('MUKURTU_HASH_SALT');
if ($salt === NULL && is_readable('/var/www/files/private/.hash_salt')) {
  $salt = rtrim((string) file_get_contents('/var/www/files/private/.hash_salt'), "\r\n");
}
if ($salt === NULL || $salt === '') {
  throw new RuntimeException('MUKURTU_HASH_SALT is required (or a salt file in the private volume)');
}
$settings['hash_salt'] = $salt;

// Requests for any other Host header are refused.
$settings['trusted_host_patterns'] = mukurtu_trusted_host_patterns(
  mukurtu_env_list(mukurtu_env_required('MUKURTU_TRUSTED_HOSTS'))
);

// Behind a reverse proxy, trust X-Forwarded-* only from the proxy's addresses.
if (mukurtu_env('MUKURTU_REVERSE_PROXY', 'false') === 'true') {
  $settings['reverse_proxy'] = TRUE;
  $settings['reverse_proxy_addresses'] = mukurtu_env_list(mukurtu_env_required('MUKURTU_REVERSE_PROXY_ADDRESSES'));
}

// Mukurtu's access control depends on the private file system.
$settings['file_private_path'] = '/var/www/files/private';
$settings['file_temp_path'] = mukurtu_env('MUKURTU_FILE_TEMP_PATH', '/tmp');
$settings['config_sync_directory'] = mukurtu_env('MUKURTU_CONFIG_SYNC_DIR', '/var/www/files/private/config-sync');

// The code is read-only to the runtime user, so Drupal must not try to
// tighten (or complain about) the permissions of sites/default.
$settings['skip_permissions_hardening'] = TRUE;

// The image is immutable and its autoloader is a complete class map, so there
// is no need to look for changed classes on every request.
$settings['class_loader_auto_detect'] = FALSE;

// Never show errors on screen. They go to the container log and the database
// log, where an administrator can read them.
$config['system.logging']['error_level'] = 'hide';
