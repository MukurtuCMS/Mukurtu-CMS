<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\CollectionQuickActionAccessHelper;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for collection preprocessing.
 */
final class CollectionPreprocessHooks {

  /**
   * Constructs a new CollectionPreprocessHooks.
   *
   * @param \Drupal\mukurtu_collection\CollectionHierarchyServiceInterface $hierarchyService
   *   Collection hierarchy service.
   * @param \Drupal\mukurtu_collection\CollectionQuickActionAccessHelper $quickActionAccessHelper
   *   Per-user cached collection-eligibility helper.
   */
  public function __construct(
    protected CollectionHierarchyServiceInterface $hierarchyService,
    protected CollectionQuickActionAccessHelper $quickActionAccessHelper,
  ) {
  }

  /**
   * Preprocesses variables for node templates.
   *
   * Called from mukurtu_collection_preprocess_node() in the .module file.
   */
  public function preprocessNode(array &$variables): void {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $variables['node'];

    // Only process collection nodes in the full view mode.
    if (!$node instanceof Collection || $variables['view_mode'] !== 'full') {
      return;
    }

    // Look up the root collection.
    $root_collection = $this->hierarchyService->getRootCollectionForCollection($node);

    if ($root_collection && $root_collection->access()) {
      // Provide the root collection as a link.
      $variables['root_collection'] = $root_collection->toLink()->toRenderable();
      $variables['root_collection']['#attributes'] = (new Attribute())->addClass('collection__root-link')->toArray();
      CacheableMetadata::createFromRenderArray($variables['root_collection'])
        ->addCacheableDependency($root_collection)
        ->applyTo($variables['root_collection']);
      // Provide a flag to indicate if the root collection has any children.
      $variables['root_collection_has_children'] = count($root_collection->getChildCollectionIds()) > 0;
    }
  }

  /**
   * Contributes Add to Collection / Add to Personal Collection to a node's
   * List-mode quick actions.
   *
   * Called from mukurtu_collection_mukurtu_browse_quick_actions_alter() in
   * the .module file, itself invoked by mukurtu_browse's
   * hook_mukurtu_browse_quick_actions_alter().
   */
  public function browseQuickActionsAlter(array &$actions, NodeInterface $node): void {
    // The mukurtu_modal query flag is how CollectionFormHooks::formAlter()
    // tells these two forms apart from their existing full-page "Add to
    // Collection" / "Add to Personal Collection" tab usage - it persists
    // across Drupal's ajax_form submission, same pattern as
    // mukurtu_media_form_alter()'s ?mukurtu_modal=1 flag.
    $dialog_attributes = [
      'class' => ['use-ajax'],
      'data-dialog-type' => 'modal',
      'data-dialog-options' => Json::encode(['width' => 600]),
    ];

    if ($this->quickActionAccessHelper->hasAddableCollectionFor($node)) {
      $icon_attributes = $dialog_attributes;
      // Distinguishes this trigger from add_to_personal_collection's below
      // (both can be present for the same node) so CollectionFormHooks can
      // return focus to the specific element that opened the dialog,
      // rather than a selector matching both.
      $icon_attributes['data-quick-action-trigger'] = 'collection-' . $node->id();
      $actions['add_to_collection'] = [
        'title' => t('Add to Collection'),
        // @node (not %node) - this fills an aria-label attribute, which
        // must be plain text; %node would wrap the value in
        // <em class="placeholder"> markup and corrupt the attribute.
        'accessible_title' => t('Add @node to Collection', ['@node' => $node->getTitle()]),
        'url' => Url::fromRoute('mukurtu_collection.add_item_to_collection', ['node' => $node->id()], ['query' => ['mukurtu_modal' => '1']]),
        'group' => 'icon',
        'icon' => 'collection-add',
        'weight' => -10,
        'attributes' => $icon_attributes,
        'cache' => [
          'tags' => $this->quickActionAccessHelper->getCacheTags(),
          'contexts' => ['user'],
        ],
      ];
    }

    if (\Drupal::currentUser()->hasPermission('add personal collection entities')) {
      $overflow_attributes = $dialog_attributes;
      $overflow_attributes['data-quick-action-trigger'] = 'personal-collection-' . $node->id();
      $actions['add_to_personal_collection'] = [
        'title' => t('Add to Personal Collection'),
        'url' => Url::fromRoute('mukurtu_collection.add_item_to_personal_collection', ['node' => $node->id()], ['query' => ['mukurtu_modal' => '1']]),
        'group' => 'overflow',
        'weight' => 10,
        'attributes' => $overflow_attributes,
        'cache' => [
          'contexts' => ['user.permissions'],
        ],
      ];
    }
  }

}
