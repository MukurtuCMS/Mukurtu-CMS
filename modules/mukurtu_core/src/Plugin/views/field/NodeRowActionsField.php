<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a per-row operations dropbutton matching the bulk action set.
 *
 * @ViewsField("mukurtu_node_row_actions")
 */
class NodeRowActionsField extends FieldPluginBase {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly AccountInterface $currentUser,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function usesGroupBy(): bool {
    return FALSE;
  }

  public function query(): void {
    $this->ensureMyTable();
  }

  public function render(ResultRow $values): array|string {
    $nid = $this->getValue($values);
    if (empty($nid)) {
      return '';
    }

    $node = $this->entityTypeManager->getStorage('node')->load($nid);
    if (!$node) {
      return '';
    }

    $links = [];

    // Edit.
    if ($node->access('update', $this->currentUser)) {
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => $node->toUrl('edit-form'),
      ];
    }

    // Publish / Unpublish.
    if ($node->access('update', $this->currentUser)) {
      $token = \Drupal::csrfToken()->get('mukurtu-node-publish-' . $nid);
      if ($node->isPublished()) {
        $links['unpublish'] = [
          'title' => $this->t('Unpublish'),
          'url' => Url::fromRoute('mukurtu_core.node.quick_unpublish', ['node' => $nid], [
            'query' => ['token' => $token],
          ]),
        ];
      }
      else {
        $links['publish'] = [
          'title' => $this->t('Publish'),
          'url' => Url::fromRoute('mukurtu_core.node.quick_publish', ['node' => $nid], [
            'query' => ['token' => $token],
          ]),
        ];
      }
    }

    // Add to export list.
    if ($this->currentUser->hasPermission('access mukurtu export')) {
      $links['add_to_export_list'] = [
        'title' => $this->t('Add to export list'),
        'url' => Url::fromRoute('mukurtu_export.add_node_to_list', ['node' => $nid]),
      ];
      $links['remove_from_export_list'] = [
        'title' => $this->t('Remove from export list'),
        'url' => Url::fromRoute('mukurtu_export.remove_node_from_list', ['node' => $nid]),
      ];
    }

    // Delete.
    if ($node->access('delete', $this->currentUser)) {
      $links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => $node->toUrl('delete-form', ['query' => ['destination' => '/admin/content']]),
      ];
    }

    if (empty($links)) {
      return '';
    }

    return [
      '#type' => 'operations',
      '#links' => $links,
    ];
  }

}
