<?php

declare(strict_types=1);

namespace Drupal\mukurtu_collection\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Template\Attribute;
use Drupal\Core\Url;
use Drupal\mukurtu_collection\CollectionHierarchyServiceInterface;
use Drupal\mukurtu_collection\CollectionQuickActionAccessHelper;
use Drupal\mukurtu_collection\Controller\MukurtuAddItemToCollectionController;
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
    // mukurtu_modal is the existing flag (see mukurtu_collection_form_alter()
    // in the .module file) that opts these forms into the AJAX modal
    // treatment already used by their full-page tabs. mukurtu_stay is new
    // and specific to this quick-action context: the tabs' shared ajax
    // callback (mukurtu_collection_add_to_x_dialog_ajax()) redirects to the
    // item's own page on success, which is correct when you're already on
    // that page but would navigate a user away from the browse listing they
    // were on when they clicked this quick action - mukurtu_stay tells that
    // callback to stay put (close the dialog, show the message, return
    // focus) instead.
    $current_user = \Drupal::currentUser();

    // Gates both branches below regardless of the user's general
    // collection-creation access - a bundle collections can't contain
    // shouldn't get an Add to Collection action just because the user
    // happens to be able to create collections in general.
    $valid_bundle = MukurtuAddItemToCollectionController::isValidCollectionItemBundle($node);
    $create_collection_access = \Drupal::entityTypeManager()
      ->getAccessControlHandler('node')
      ->createAccess('collection', $current_user, [], TRUE);

    if ($valid_bundle && ($this->quickActionAccessHelper->hasAddableCollectionFor($node) || $create_collection_access->isAllowed())) {
      $actions['add_to_collection'] = [
        'title' => t('Add to Collection'),
        'url' => Url::fromRoute('mukurtu_collection.add_item_to_collection', ['node' => $node->id()], ['query' => ['mukurtu_modal' => '1', 'mukurtu_stay' => '1']]),
        'group' => 'overflow',
        'weight' => 5,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 560]),
          'data-quick-action-trigger' => 'collection-' . $node->id(),
        ],
        'cache' => [
          'tags' => array_merge($this->quickActionAccessHelper->getCacheTags(), $create_collection_access->getCacheTags()),
          'contexts' => array_merge(['user'], $create_collection_access->getCacheContexts()),
        ],
      ];
    }

    if ($current_user->hasPermission('add personal collection entities')) {
      $actions['add_to_personal_collection'] = [
        'title' => t('Add to Personal Collection'),
        'url' => Url::fromRoute('mukurtu_collection.add_item_to_personal_collection', ['node' => $node->id()], ['query' => ['mukurtu_modal' => '1', 'mukurtu_stay' => '1']]),
        'group' => 'overflow',
        'weight' => 10,
        'attributes' => [
          'class' => ['use-ajax'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 900]),
          'data-quick-action-trigger' => 'personal-collection-' . $node->id(),
        ],
        'cache' => [
          'contexts' => ['user.permissions'],
        ],
      ];
    }
  }

}
