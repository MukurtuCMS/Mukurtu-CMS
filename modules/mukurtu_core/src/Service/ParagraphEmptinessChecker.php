<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\paragraphs\ParagraphInterface;

/**
 * Determines whether a paragraph has no content of its own.
 */
class ParagraphEmptinessChecker {

  /**
   * Checks whether a paragraph has no content of its own.
   *
   * A paragraph is considered empty when none of its own content fields
   * (i.e. fields prefixed "field_") have a value. Base/bookkeeping fields
   * (id, revision, parent references, behavior settings, etc.) are ignored.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $paragraph
   *   The paragraph to check.
   *
   * @return bool
   *   TRUE if all of the paragraph's content fields are empty.
   */
  public function isEmpty(ParagraphInterface $paragraph): bool {
    foreach ($paragraph->getFields() as $field_name => $field_item_list) {
      if (str_starts_with($field_name, 'field_') && !$field_item_list->isEmpty()) {
        return FALSE;
      }
    }

    return TRUE;
  }

}
