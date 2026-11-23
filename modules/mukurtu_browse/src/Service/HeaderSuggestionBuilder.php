<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\Service;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\search_api\Query\QueryInterface;

/**
 * Builds the items the header search suggests, grouped by content type.
 *
 * The query passed in carries the search's own access checks and filters,
 * so groups, counts and items only reflect content the visitor can see.
 */
class HeaderSuggestionBuilder {

  /**
   * How many items each group shows.
   */
  public const ITEMS_PER_GROUP = 3;

  /**
   * How many content types are shown, largest first.
   */
  public const MAX_GROUPS = 5;

  /**
   * Constructs a HeaderSuggestionBuilder.
   */
  public function __construct(
    protected EntityRepositoryInterface $entityRepository,
    protected EntityTypeBundleInfoInterface $bundleInfo,
    protected BrowseLinkBuilder $linkBuilder,
  ) {}

  /**
   * Returns suggested items for a search query, grouped by content type.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query with the search keys set. It is cloned, not executed.
   * @param string $keys
   *   The search keys, for the links to the full results.
   * @param bool $link_groups
   *   Whether each group links to Browse filtered to its content type.
   *
   * @return array
   *   A list of groups, each with:
   *   - label: The content type's name.
   *   - count: How many items of that type match.
   *   - more_url: Browse filtered to the type and keys, or NULL when the
   *     group shows every match or groups aren't linked.
   *   - items: A list of items, each with a label and url.
   */
  public function build(QueryInterface $query, string $keys, bool $link_groups): array {
    $query = clone $query;
    // Facets replaces the facets of any query that carries its view with the
    // view's own, which would drop the content type counts below whenever
    // the view has no content type facet. Nothing else reads this option
    // outside the view's own plugins.
    $query->setOption('search_api_view', NULL);

    $count_query = clone $query;
    $count_query->range(0, 0);
    $count_query->setOption('search_api_facets', [
      'type' => [
        'field' => 'type',
        'limit' => static::MAX_GROUPS,
        'operator' => 'and',
        'min_count' => 1,
        'missing' => FALSE,
      ],
    ]);
    $facets = $count_query->execute()->getExtraData('search_api_facets', [])['type'] ?? [];
    $bundles = $this->bundleInfo->getBundleInfo('node');

    $groups = [];
    foreach (array_slice($facets, 0, static::MAX_GROUPS) as $facet) {
      $bundle = trim((string) $facet['filter'], '"');
      $items = $this->items($query, $bundle);
      if (!$items) {
        continue;
      }
      $count = (int) $facet['count'];
      $groups[] = [
        'label' => (string) ($bundles[$bundle]['label'] ?? $bundle),
        'count' => $count,
        // Only when the group has more than it shows.
        'more_url' => $link_groups && $count > count($items)
          ? $this->linkBuilder->getTypeBrowseUrl($bundle, $keys)->toString(TRUE)->getGeneratedUrl()
          : NULL,
        'items' => $items,
      ];
    }
    return $groups;
  }

  /**
   * Returns the most relevant items of one content type.
   */
  protected function items(QueryInterface $query, string $bundle): array {
    $group_query = clone $query;
    $group_query->addCondition('type', $bundle);
    $group_query->range(0, static::ITEMS_PER_GROUP);
    $sorts = &$group_query->getSorts();
    $sorts = ['search_api_relevance' => QueryInterface::SORT_DESC];

    $items = [];
    foreach ($group_query->execute()->getResultItems() as $item) {
      $entity = $item->getOriginalObject()?->getValue();
      if (!$entity instanceof EntityInterface || !$item->getAccessResult()->isAllowed()) {
        continue;
      }
      $entity = $this->entityRepository->getTranslationFromContext($entity);
      $items[] = [
        'label' => (string) $entity->label(),
        'url' => $entity->toUrl()->toString(TRUE)->getGeneratedUrl(),
      ];
    }
    return $items;
  }

}
