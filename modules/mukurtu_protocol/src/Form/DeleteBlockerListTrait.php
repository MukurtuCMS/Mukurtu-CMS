<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Session\AccountInterface;

/**
 * Builds lists of the entities that block a delete.
 *
 * Used by the protocol and community delete forms to explain why an entity
 * can't be deleted. Every blocking entity is access checked so the counts are
 * exact, but only the ones the user can view are linked.
 */
trait DeleteBlockerListTrait {

  /**
   * The most viewable blocking entities to link.
   *
   * @var int
   */
  protected int $blockerLinkLimit = 20;

  /**
   * How many entities to load at a time while access checking.
   *
   * @var int
   */
  protected int $blockerLoadChunkSize = 50;

  /**
   * Sorts blocking entities into viewable and restricted.
   *
   * @param array $ids_by_type
   *   Entity IDs keyed by entity type ID.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user viewing the list.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cache metadata of the access checks.
   *
   * @return array
   *   An array with keys:
   *   - items: item_list items linking to up to $blockerLinkLimit viewable
   *     entities.
   *   - viewable: the number of blocking entities the user can view.
   *   - restricted: the number of blocking entities the user can't view.
   */
  protected function sortDeleteBlockers(array $ids_by_type, AccountInterface $account, CacheableMetadata $cacheability): array {
    $items = [];
    $viewable = 0;
    $restricted = 0;

    foreach ($ids_by_type as $entity_type_id => $ids) {
      $storage = $this->entityTypeManager->getStorage($entity_type_id);
      $bundle_info = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
      $cacheability->addCacheTags($storage->getEntityType()->getListCacheTags());

      foreach (array_chunk($ids, $this->blockerLoadChunkSize) as $chunk) {
        foreach ($storage->loadMultiple($chunk) as $entity) {
          $access = $entity->access('view', $account, TRUE);
          $cacheability->addCacheableDependency($access);
          if (!$access->isAllowed()) {
            $restricted++;
            continue;
          }

          $viewable++;
          if (count($items) < $this->blockerLinkLimit) {
            $entity = $this->entityRepository->getTranslationFromContext($entity);
            $cacheability->addCacheableDependency($entity);
            $bundle_label = $entity->getEntityType()->hasKey('bundle') ? ($bundle_info[$entity->bundle()]['label'] ?? NULL) : NULL;
            $items[] = [
              '#type' => 'inline_template',
              '#template' => '{{ link }}{% if bundle %} ({{ bundle }}){% endif %}',
              '#context' => [
                'link' => $entity->toLink()->toRenderable(),
                'bundle' => $bundle_label,
              ],
            ];
          }
        }
        // Free memory before loading the next chunk.
        $storage->resetCache($chunk);
      }
    }

    return [
      'items' => $items,
      'viewable' => $viewable,
      'restricted' => $restricted,
    ];
  }

  /**
   * Builds a warning message render array.
   *
   * Uses the same markup and classes as a status message, without the
   * contentinfo landmark, the data-drupal-messages container, or the dismiss
   * button. The warning sits inside the page content, explains the page, and
   * must not become the target for JavaScript status messages.
   *
   * @param array $message
   *   The message render array.
   *
   * @return array
   *   A render array.
   */
  protected function buildDeleteBlockedWarning(array $message): array {
    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['messages', 'messages--warning', 'mukurtu-delete-blocked'],
      ],
      'header' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages__header']],
        'title' => [
          '#type' => 'html_tag',
          '#tag' => 'h2',
          '#value' => $this->t('Warning message'),
          '#attributes' => ['class' => ['messages__title']],
        ],
      ],
      'content' => [
        '#type' => 'container',
        '#attributes' => ['class' => ['messages__content']],
        'message' => $message,
      ],
      '#attached' => ['library' => ['mukurtu_protocol/delete-blocked']],
    ];
  }

  /**
   * Builds the heading, links, and "more" line for viewable blockers.
   *
   * @param string|\Stringable $heading
   *   The list heading.
   * @param array $sorted
   *   The result of sortDeleteBlockers().
   *
   * @return array
   *   A render array, empty when the user can't view any blockers.
   */
  protected function buildDeleteBlockerList($heading, array $sorted): array {
    if (empty($sorted['items'])) {
      return [];
    }

    $build = [
      'heading' => [
        '#type' => 'html_tag',
        '#tag' => 'h2',
        '#value' => $heading,
      ],
      'list' => [
        '#theme' => 'item_list',
        '#items' => $sorted['items'],
      ],
    ];

    $more = $sorted['viewable'] - count($sorted['items']);
    if ($more > 0) {
      $build['more'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->formatPlural($more, 'And 1 more item you can view.', 'And @count more items you can view.'),
      ];
    }

    return $build;
  }

}
