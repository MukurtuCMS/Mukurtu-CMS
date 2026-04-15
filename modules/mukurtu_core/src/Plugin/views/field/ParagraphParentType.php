<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Provides Paragraph Parent Type field handler.
 *
 * @ViewsField("paragraph_parent_type")
 */
class ParagraphParentType extends FieldPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values)
  {
    $paragraph_entity = $values->_entity;
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
    $parent = $paragraph_entity->getParentEntity();
    $bundleInfo = \Drupal::service('entity_type.bundle.info')->getBundleInfo('node');

    // If the paragraph's parent entity is a node, get the bundle type and
    // return that.
    if ($parent instanceof Node) {
      // This approach is kind of heavy-handed, but I couldn't find another way
      // to get the nice, human-readable bundle name.
      return $this->sanitizeValue($bundleInfo[$parent->bundle()]['label']);
    }
    else {
      return $this->sanitizeValue("Paragraph");
    }
  }

  public function query() {}
}
