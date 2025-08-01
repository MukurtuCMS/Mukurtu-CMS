<?php

namespace Drupal\mukurtu_protocol\Entity;

use Drupal\Core\Entity\EntityViewBuilder;

/**
 * Provides a custom view builder for the Protocol entity.
 *
 * {@inheritdoc}
 */
class ProtocolViewBuilder extends EntityViewBuilder {
  public function build(array $build) {
    $build = parent::build($build);
    // The entity system by default does not use separate view mode templates.
    // Define a suggestion pattern that includes the view mode. For example,
    // this will check protocol--full.html.twig then protocol.html.twig.
    $build['#theme'] = 'protocol__' . $build['#view_mode'];
    return $build;
  }
}
