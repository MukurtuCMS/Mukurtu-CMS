<?php

namespace Drupal\mukurtu_protocol\Plugin\views\field;

use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Drupal\mukurtu_protocol\Entity\MukurtuUser;

/**
 * Provides Community field handler.
 *
 * @ViewsField("user_community")
 */
class UserCommunity extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\node\Entity\Node $node */
    if ($values->_entity instanceof MukurtuUser) {
      $communities = array_filter($values->_entity->getCommunities(), fn ($c) => $c->access('view'));
      $names = array_map(fn ($c) => $c->getName(), $communities);
      asort($names);

      if (!empty($names)) {
        return [
          '#theme' => 'item_list',
          '#list_type' => 'ul',
          '#items' => $names,
        ];
      }
    }
    return "";
  }

  public function query() {
  }

}
