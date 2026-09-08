<?php

namespace Drupal\mukurtu_core\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * CitationItemList class to generate a computed field.
 */
class CitationItemList extends FieldItemList
{
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue()
  {
    $config = \Drupal::config('mukurtu.settings');
    $entity = $this->getEntity();
    $targetBundle = $entity->bundle();

    $targetTemplate = $config->get("citation_templates.$targetBundle") ?? '';

    $tokenService = \Drupal::service("token");

    // Token::replace() requires a keyed array of token types.
    // Some tokens are not replaced by default.
    $data = [
      'node' => $entity,
      'language' => $entity->language(),
      'random' => $entity,
    ];

    // Clear tokens that do not have a replacement value.
    $options = [
      'clear' => TRUE
    ];

    $citation = $tokenService->replace($targetTemplate, $data, $options);
    $citation = $this->cleanCitationArtifacts($citation);

    $this->list[0] = $this->createItem(0, ['value' => $citation, 'format' => 'basic_html']);
  }

  /**
   * Collapses stray punctuation/whitespace left behind by empty tokens.
   *
   * Empty tokens are cleared to '' by Token::replace(), but the literal
   * separator punctuation an admin placed around the token in the
   * template (commas, periods, semicolons, colons) is left behind,
   * producing artifacts like "Title, , 2020." or "Title. .".
   */
  private function cleanCitationArtifacts(string $citation): string
  {
    // Collapse whitespace runs to a single space.
    $citation = preg_replace('/\s+/', ' ', $citation);

    // Drop whitespace immediately before punctuation.
    $citation = preg_replace('/\s+([,;:.])/', '$1', $citation);

    // Collapse runs of adjacent separator punctuation down to the last
    // mark in the run (an empty token typically leaves its neighboring
    // separator behind, e.g. ", ," or ". .").
    do {
      $citation = preg_replace('/[,;:.]\s*(?=[,;:.])/', '', $citation, -1, $count);
    } while ($count > 0);

    // Strip a stray leading separator left when the first token is empty.
    $citation = preg_replace('/^[\s,;:.]+/', '', $citation);

    return trim($citation);
  }

}
