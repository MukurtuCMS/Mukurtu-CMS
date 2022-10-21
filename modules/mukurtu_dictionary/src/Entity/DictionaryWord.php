<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_dictionary\Entity\DictionaryWordInterface;
use Drupal\Core\Session\AccountInterface;
use \Drupal\Core\Entity\EntityStorageInterface;

class DictionaryWord extends Node implements DictionaryWordInterface {
  /**
   * {@inheritdoc}
   */
  public function access($operation = 'view', AccountInterface $account = NULL, $return_as_object = FALSE) {
    return parent::access($operation, $account, $return_as_object);
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage)
  {
    if ($this->hasField('field_glossary_entry')) {
      if (empty($this->get('field_glossary_entry')->getValue())) {
        $this->set("field_glossary_entry", $this->getTitle()[0]);
      }
    }
  }

}
