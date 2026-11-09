<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\mukurtu_search\NestedTermFacets;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects browse links that filter by term name to the term's ID (#2522).
 *
 * Term facets on the browse and dictionary pages used term names, so older
 * bookmarks and links carry them, for example ?category[Arts]=Arts. The
 * facets now use term IDs. A name that matches exactly one term in the
 * facet's vocabularies is swapped for its ID; anything else is left alone.
 */
class TermFacetNameRedirectSubscriber implements EventSubscriberInterface {

  /**
   * The database search view behind each browse page, keyed by route name.
   */
  const PAGES = [
    'mukurtu_browse.browse_page' => 'mukurtu_browse',
    'mukurtu_browse.browse_digital_heritage_page' => 'mukurtu_digital_heritage_browse',
    'mukurtu_dictionary.dictionary_page' => 'mukurtu_dictionary',
  ];

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ?NestedTermFacets $nestedTermFacets = NULL,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // After routing (32), and before the Dynamic Page Cache (27) can serve a
    // cached response for the route.
    return [KernelEvents::REQUEST => ['redirectTermNames', 30]];
  }

  /**
   * Redirects to the same page with term names swapped for term IDs.
   */
  public function redirectTermNames(RequestEvent $event): void {
    $request = $event->getRequest();
    $route_name = (string) $request->attributes->get('_route');
    // The service is optional so that Kernel tests can enable this module
    // without mukurtu_search.
    if (!$this->nestedTermFacets || !$event->isMainRequest() || !isset(self::PAGES[$route_name]) || !$request->query->count()) {
      return;
    }
    if (($this->configFactory->get('mukurtu_search.settings')->get('backend') ?? 'db') !== 'db') {
      return;
    }

    $query = $request->query->all();
    $changed = FALSE;
    foreach ($this->getTermFacets(self::PAGES[$route_name]) as $identifier => $vids) {
      if (!isset($query[$identifier]) || !is_array($query[$identifier])) {
        continue;
      }
      $values = [];
      foreach ($query[$identifier] as $key => $value) {
        $tid = ctype_digit((string) $key) ? NULL : $this->findTerm($vids, (string) $key);
        if ($tid === NULL) {
          $values[$key] = $value;
          continue;
        }
        $values[$tid] = $tid;
        $changed = TRUE;
      }
      $query[$identifier] = $values;
    }

    if ($changed) {
      $url = Url::fromRoute($route_name, $request->attributes->get('_raw_variables')?->all() ?? [], ['query' => $query]);
      $response = new LocalRedirectResponse($url->toString(), 301);
      // The redirect is only right for this query string. Without this, the
      // Dynamic Page Cache would serve it for every query on the page.
      $response->getCacheableMetadata()
        ->addCacheContexts(['url.query_args'])
        // Renaming or deleting a term changes where the name points.
        ->addCacheTags(['taxonomy_term_list']);
      $event->setResponse($response);
    }
  }

  /**
   * Gets a view's facet filters on term IDs.
   *
   * @return array
   *   The vocabularies each facet can hold (empty for any), keyed by the
   *   facet's URL identifier.
   */
  protected function getTermFacets(string $view_id): array {
    $facets = [];
    $filters = $this->configFactory->get("views.view.$view_id")->get('display.default.display_options.filters') ?? [];
    foreach ($filters as $filter) {
      if (($filter['plugin_id'] ?? NULL) !== 'facets_filter' || !str_starts_with((string) ($filter['table'] ?? ''), 'search_api_index_')) {
        continue;
      }
      $index = $this->entityTypeManager->getStorage('search_api_index')->load(substr($filter['table'], strlen('search_api_index_')));
      if (!$index) {
        continue;
      }
      $fields = $this->nestedTermFacets->termIdFieldVocabularies($index);
      $field_id = substr((string) ($filter['field'] ?? ''), strlen('facets_'));
      $identifier = $filter['expose']['identifier'] ?? NULL;
      if ($identifier && isset($fields[$field_id])) {
        $facets[$identifier] = $fields[$field_id];
      }
    }
    return $facets;
  }

  /**
   * Finds the one term with a name, in the given vocabularies.
   *
   * @return string|null
   *   The term ID, or NULL if no term or more than one has the name.
   */
  protected function findTerm(array $vids, string $name): ?string {
    $query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(TRUE)
      ->condition('name', $name)
      ->range(0, 2);
    if ($vids) {
      $query->condition('vid', $vids, 'IN');
    }
    $ids = $query->execute();
    return count($ids) === 1 ? (string) reset($ids) : NULL;
  }

}
