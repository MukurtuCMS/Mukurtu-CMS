<?php

namespace Drupal\mukurtu_collection\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a custom view builder for the Personal collection entity.
 *
 * {@inheritdoc}
 */
class PersonalCollectionViewBuilder extends EntityViewBuilder {
  public function build(array $build) {
    $build = parent::build($build);
    // The entity system by default does not use separate view mode templates.
    // Define a suggestion pattern that includes the view mode. For example,
    // this will check personal_collection--full.html.twig then
    // personal_collection.html.twig.
    $build['#theme'] = 'personal_collection__' . $build['#view_mode'];
    return $build;
  }
}
