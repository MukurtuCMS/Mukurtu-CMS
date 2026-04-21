<?php

namespace Drupal\message_ui\Plugin\views\field;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Presenting contextual links to the messages view.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("message_ui_contextual_links")
 */
class MessageUIContextualLinks extends FieldPluginBase {

  /**
   * Stores the result of message_view_multiple for all rows to reuse it later.
   *
   * @var array
   */
  protected $build;

  /**
   * {@inheritdoc}
   */
  public function query() {
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    /** @var \Drupal\message_ui\MessageUiViewsContextualLinksManager $contextual_links */
    $contextual_links = \Drupal::service('plugin.manager.message_ui_views_contextual_links');

    $links = [];

    // Iterate over the plugins.
    foreach ($contextual_links->getDefinitions() as $plugin) {
      /** @var \Drupal\message_ui\MessageUiViewsContextualLinksInterface $contextual_link */
      $contextual_link = $contextual_links->createInstance($plugin['id']);
      $contextual_link->setMessage($values->_entity);

      $access = $contextual_link->access();
      if ($access instanceof AccessResultInterface) {
        $access = $access->isAllowed();
      }

      if (!$access || !$link = $contextual_link->getRouterInfo()) {
        // Nothing happens in the plugin. Skip.
        continue;
      }

      $link['attributes'] = ['class' => [$plugin['id']]];

      $links[$plugin['id']] = $link + ['weight' => $plugin['weight']];
    }

    usort($links, ['Drupal\Component\Utility\SortArray', 'sortByWeightElement']);

    $row['operations']['data'] = [
      '#type' => 'operations',
      '#links' => $links,
    ];

    return $row;
  }

}
