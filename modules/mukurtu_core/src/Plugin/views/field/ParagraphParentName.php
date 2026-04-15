<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\node\Entity\Node;

/**
 * Provides Paragraph Parent Name field handler.
 *
 * @ViewsField("paragraph_parent_name")
 */
class ParagraphParentName extends FieldPluginBase
{

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values)
  {
    $paragraph_entity = $values->_entity;
    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph_entity */
    $parent = $paragraph_entity->getParentEntity();
    return $this->sanitizeValue($parent->label());
  }

  public function query() {}
}
