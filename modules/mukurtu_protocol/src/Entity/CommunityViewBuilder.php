<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a custom view builder for the Community entity.
 *
 * {@inheritdoc}
 */
class CommunityViewBuilder extends EntityViewBuilder {
  public function build(array $build) {
    $build = parent::build($build);
    // The entity system by default does not use separate view mode templates.
    // Define a suggestion pattern that includes the view mode. For example,
    // this will check community--full.html.twig then community.html.twig.
    $build['#theme'] = 'community__' . $build['#view_mode'];
    return $build;
  }
}
