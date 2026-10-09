<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\node\NodeInterface;
use Drupal\taxonomy\TermInterface;

interface DictionaryWordInterface extends NodeInterface {

  /**
   * Gets the term for the language this word belongs to.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The language term, or NULL if none is set.
   */
  public function getLanguageTerm(): ?TermInterface;

  /**
   * Gets the language code to use as this word's HTML lang attribute.
   *
   * @return string|null
   *   The code from the language term's Language code field, or NULL if the
   *   term has no code.
   */
  public function getLanguageCode(): ?string;

}
