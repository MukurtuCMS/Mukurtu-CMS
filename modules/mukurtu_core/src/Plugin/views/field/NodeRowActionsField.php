<?php

namespace Drupal\mukurtu_core\Plugin\views\field;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\content_moderation\ModerationInformationInterface;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolver;
use Drupal\mukurtu_core\Service\ModerationTransitionAccessResolverInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders a per-row operations dropbutton for the content admin view.
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
    protected readonly ModerationInformationInterface $moderationInfo,
    protected readonly ModerationTransitionAccessResolverInterface $transitionAccess,
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
      $container->get('content_moderation.moderation_information'),
      // Not a services.yml entry: mukurtu_core has no formal dependency on
      // content_moderation, and other content_moderation-consuming code
      // here follows the same lazy, plugin-local instantiation pattern
      // rather than an eagerly-compiled container service, so mukurtu_core
      // stays usable in contexts (including many existing Kernel tests)
      // that never enable content_moderation.
      new ModerationTransitionAccessResolver($container->get('content_moderation.state_transition_validation')),
    );
  }

  public function usesGroupBy(): bool {
    return FALSE;
  }

  public function query(): void {
    parent::query();
  }

  public function render(ResultRow $values): array|string {
    try {
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

      // Moderated bundles: one link per transition actually valid for this
      // node and viewer right now (both the real per-transition entity
      // access AND the transition's own legality -- neither check alone
      // suffices). Archive/restore only require 'view' (they're pure
      // moderation decisions, not content edits); every other transition
      // still requires 'update' -- see ModerationTransitionAccessResolver.
      // Non-moderated bundles (e.g. landing_page): no moderation handler is
      // fighting a direct publish flag, so the plain publish/unpublish
      // mechanism remains correct there.
      if ($this->moderationInfo->isModeratedEntity($node)) {
        foreach ($this->transitionAccess->getAccessibleTransitions($node, $this->currentUser) as $transition) {
          $to_state = $transition->to()->id();
          $url = Url::fromRoute('mukurtu_core.node.moderation_transition', ['node' => $nid, 'to_state' => $to_state]);
          $url->setOption('query', ['token' => \Drupal::csrfToken()->get(ltrim($url->getInternalPath(), '/'))]);
          $links['transition_' . $to_state] = ['title' => $transition->label(), 'url' => $url];
        }
      }
      elseif ($node->access('update', $this->currentUser)) {
        if ($node->isPublished()) {
          $url = Url::fromRoute('mukurtu_core.node.quick_unpublish', ['node' => $nid]);
          $url->setOption('query', ['token' => \Drupal::csrfToken()->get(ltrim($url->getInternalPath(), '/'))]);
          $links['unpublish'] = ['title' => $this->t('Unpublish'), 'url' => $url];
        }
        else {
          $url = Url::fromRoute('mukurtu_core.node.quick_publish', ['node' => $nid]);
          $url->setOption('query', ['token' => \Drupal::csrfToken()->get(ltrim($url->getInternalPath(), '/'))]);
          $links['publish'] = ['title' => $this->t('Publish'), 'url' => $url];
        }
      }

      // Export and export list operations.
      if ($this->currentUser->hasPermission('access mukurtu export')) {
        $links['export'] = [
          'title' => $this->t('Export'),
          'url' => Url::fromRoute('mukurtu_export.start_adhoc_node', ['node' => $nid]),
        ];
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
    catch (\Exception $e) {
      \Drupal::logger('mukurtu_core')->error('Row actions render error for nid @nid: @msg', [
        '@nid' => $this->getValue($values),
        '@msg' => $e->getMessage(),
      ]);
      return '';
    }
  }

}
