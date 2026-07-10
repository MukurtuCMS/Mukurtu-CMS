<?php

/**
 * @file
 * The PHP page that serves all page requests on a Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 *
 * This is a local override of drupal/core's scaffolded index.php. Core's
 * version requires 'autoload_runtime.php' with no path prefix, which only
 * resolves if PHP's current working directory happens to match the
 * directory containing vendor/autoload_runtime.php. Real web server
 * requests set the working directory to the docroot (this file's own
 * directory), one level below vendor/, so the bare require fails with
 * "Failed opening required 'autoload_runtime.php'". Using an explicit
 * path makes this independent of the working directory.
 */

use Drupal\Core\DrupalKernel;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

return static function () {
  return new DrupalKernel('prod', require __DIR__ . '/autoload.php');
};
