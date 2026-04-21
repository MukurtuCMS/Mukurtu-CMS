<?php

namespace Drupal\mukurtu_protocol\Plugin\views\field;

use Drupal\Core\Link;
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
      uasort($communities, fn ($a, $b) => strcmp($a->getName(), $b->getName()));
      $links = array_map(fn ($c) => Link::fromTextAndUrl($c->getName(), $c->toUrl())->toString(), $communities);

      if (!empty($links)) {
        return ['#markup' => implode(', ', $links)];
      }
    }
    return "";
  }

  public function query() {
  }

}
