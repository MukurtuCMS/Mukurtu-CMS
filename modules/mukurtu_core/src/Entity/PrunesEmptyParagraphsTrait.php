<?php

namespace Drupal\mukurtu_core\Entity;

use Drupal\paragraphs\ParagraphInterface;

/**
 * @mixin \Drupal\Core\Entity\ContentEntityInterface
 */
trait PrunesEmptyParagraphsTrait {

  /**
   * Drops newly-created, entirely-blank paragraphs from a field before save.
   *
   * The Paragraphs widget auto-attaches a blank paragraph to single-bundle
   * fields on new-content forms so editors can see what fields it holds.
   * If the editor leaves it untouched, this prevents that blank paragraph
   * from ever being persisted. Only paragraphs that are still new (never
   * saved) are considered -- an existing, already-saved paragraph that a
   * user has emptied out is a separate editing concern.
   *
   * @param string $field_name
   *   The paragraph reference field to prune.
   */
  protected function pruneEmptyParagraphs(string $field_name): void {
    if (!$this->hasField($field_name)) {
      return;
    }

    $checker = \Drupal::service('mukurtu_core.paragraph_emptiness_checker');
    $field = $this->get($field_name);
    $values = [];
    foreach ($field as $item) {
      $paragraph = $item->entity ?? NULL;
      if ($paragraph instanceof ParagraphInterface && $paragraph->isNew() && $checker->isEmpty($paragraph)) {
        continue;
      }
      $values[] = $item->getValue();
    }
    $field->setValue($values);
  }

}
