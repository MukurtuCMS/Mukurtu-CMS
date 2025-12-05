<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Entity;

/**
 * A person or people represented or referenced in content.
 */
interface PeopleInterface {

  /**
   * Get the person or people represented or referenced in this content.
   *
   * @return \Drupal\taxonomy\TermInterface[]
   *   The person or people represented or referenced in this content.
   */
  public function getPeopleTerms(): array;

}
