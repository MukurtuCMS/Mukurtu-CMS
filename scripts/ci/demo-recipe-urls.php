<?php

/**
 * @file
 * Prints the page path of each page-level entity a demo recipe created.
 *
 * Usage: drush php:script scripts/ci/demo-recipe-urls.php <recipe content dir>
 *
 * Reads the UUID of every node, community, protocol and multipage item in the
 * recipe's content directory, loads it, and prints its canonical path, one
 * per line. Fails if any of them wasn't created. CI requests each path to
 * check that the page renders.
 */

use Drupal\Component\Serialization\Yaml;

$dir = $extra[0] ?? NULL;
if (!$dir || !is_dir($dir)) {
  throw new \RuntimeException("Pass the absolute path to the recipe's content directory.");
}

$entity_type_manager = \Drupal::entityTypeManager();
$missing = 0;

foreach (['node', 'community', 'protocol', 'multipage_item'] as $entity_type_id) {
  foreach (glob("$dir/$entity_type_id/*.yml") ?: [] as $file) {
    $uuid = Yaml::decode(file_get_contents($file))['_meta']['uuid'] ?? NULL;
    $entities = $uuid ? $entity_type_manager->getStorage($entity_type_id)->loadByProperties(['uuid' => $uuid]) : [];
    $entity = reset($entities);
    if (!$entity) {
      fwrite(STDERR, "Not created: $entity_type_id $uuid ($file)\n");
      $missing++;
      continue;
    }
    print $entity->toUrl()->toString() . PHP_EOL;
  }
}

// Drush treats any exit() as abnormal, so fail by throwing instead.
if ($missing) {
  throw new \RuntimeException("$missing recipe entities weren't created.");
}
