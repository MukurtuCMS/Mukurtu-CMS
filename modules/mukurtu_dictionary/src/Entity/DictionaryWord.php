<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\Core\Session\AccountInterface;

class DictionaryWord extends Node implements DictionaryWordInterface {
  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return parent::access($operation, $account, $return_as_object);
  }
}
