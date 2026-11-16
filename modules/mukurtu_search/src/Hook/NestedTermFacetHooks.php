<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mukurtu_search\NestedTermFacets;
use Drupal\search_api\IndexInterface;
use Drupal\views\ViewEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps facets on taxonomy term IDs nesting as indexes and views change.
 *
 * @see \Drupal\mukurtu_search\NestedTermFacets
 */
class NestedTermFacetHooks {

  public function __construct(
    #[Autowire(service: 'mukurtu_search.nested_term_facets')]
    protected NestedTermFacets $nestedTermFacets,
  ) {}

  /**
   * Implements hook_ENTITY_TYPE_presave() for search_api_index.
   */
  #[Hook('search_api_index_presave')]
  public function indexPresave(IndexInterface $index): void {
    $this->nestedTermFacets->applyToIndex($index);
  }

  /**
   * Implements hook_ENTITY_TYPE_presave() for view.
   */
  #[Hook('view_presave')]
  public function viewPresave(ViewEntityInterface $view): void {
    $this->nestedTermFacets->applyToView($view);
  }

}
