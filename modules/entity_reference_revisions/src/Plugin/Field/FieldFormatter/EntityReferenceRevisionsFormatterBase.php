<?php

namespace Drupal\entity_reference_revisions\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceFormatterBase;

/**
 * Parent plugin for entity reference formatters.
 */
abstract class EntityReferenceRevisionsFormatterBase extends EntityReferenceFormatterBase {

  /**
   * {@inheritdoc}
   */
  public function prepareView(array $entities_items) {

    // Unlike the parent, do not optimize to load across multiple entities.
    // that is a rare case and would require to duplicate the logic in
    // \Drupal\entity_reference_revisions\EntityReferenceRevisionsFieldItemList::referencedEntities().
    // That uses entity revision caching on Drupal 11.3+ and bulk loads default revisions first in Drupal 11.2
    // and older.
    foreach ($entities_items as $items) {
      assert($items instanceof EntityReferenceFieldItemListInterface);
      $revisions = $items->referencedEntities();

      foreach ($revisions as $delta => $revision) {
        $items[$delta]->entity = $revision;
        $items[$delta]->_loaded = TRUE;
      }
    }
  }

}
