<?php

/**
 * @file
 * The PHP page that handles updating the Drupal installation.
 *
 * All Drupal code is released under the GNU General Public License.
 * See COPYRIGHT.txt and LICENSE.txt files in the "core" directory.
 *
 * This is a local override of drupal/core's scaffolded update.php. See
 * scaffold/index.php for why the bare 'autoload_runtime.php' require is
 * replaced with an explicit path.
 */

use Drupal\Core\Update\UpdateKernel;

require_once __DIR__ . '/../vendor/autoload_runtime.php';

// Disable garbage collection during test runs. Under certain circumstances the
// update path will create so many objects that garbage collection causes
// segmentation faults.
if (drupal_valid_test_ua()) {
  gc_collect_cycles();
  gc_disable();
}

return static function () {
  return new UpdateKernel('prod', require __DIR__ . '/autoload.php', FALSE);
};
