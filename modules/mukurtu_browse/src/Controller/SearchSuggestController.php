<?php

declare(strict_types=1);

namespace Drupal\mukurtu_browse\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_browse\Service\HeaderSuggestionBuilder;
use Drupal\search_api\SearchApiException;
use Drupal\search_api_autocomplete\SearchApiAutocompleteException;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Utility\AutocompleteHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns the items the header search suggests, grouped by content type.
 *
 * Queries run through the same Search API Autocomplete search, and so the
 * same view, as the word suggestions on the page the header searches. Only
 * the database backend is supported.
 */
class SearchSuggestController extends ControllerBase {

  /**
   * The searches behind each page the header search can point to.
   */
  protected const PAGES = [
    '/browse' => [
      'search' => 'mukurtu_browse',
      'display' => 'mukurtu_browse_block',
      'route' => 'mukurtu_browse.browse_page',
      'link_groups' => TRUE,
    ],
    '/digital-heritage' => [
      'search' => 'mukurtu_digital_heritage_browse',
      'display' => 'mukurtu_digital_heritage_browse_block',
      'route' => 'mukurtu_browse.browse_digital_heritage_page',
      // Everything here is one content type, so "See all results" is enough.
      'link_groups' => FALSE,
    ],
  ];

  /**
   * Constructs a SearchSuggestController.
   */
  public function __construct(
    protected AutocompleteHelperInterface $autocompleteHelper,
    protected HeaderSuggestionBuilder $builder,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('search_api_autocomplete.helper'),
      $container->get('mukurtu_browse.header_suggestion_builder'),
    );
  }

  /**
   * Returns the settings for the page the header search points to, if any.
   */
  protected function page(): ?array {
    $config = $this->config('mukurtu_search.settings');
    if (($config->get('backend') ?? 'db') !== 'db'
      || !($config->get('header_search_enabled') ?? TRUE)
      || !($config->get('header_suggestions_enabled') ?? TRUE)) {
      return NULL;
    }
    return static::PAGES[$config->get('header_search_path') ?? '/browse'] ?? NULL;
  }

  /**
   * Returns the autocomplete search behind the header search, if any.
   */
  public function search(): ?SearchInterface {
    $page = $this->page();
    if (!$page) {
      return NULL;
    }
    return $this->entityTypeManager()->getStorage('search_api_autocomplete_search')->load($page['search']);
  }

  /**
   * Checks whether the account may get header suggestions.
   */
  public function access(AccountInterface $account): AccessResultInterface {
    $search = $this->search();
    $result = $search
      ? $this->autocompleteHelper->access($search, $account)
      : AccessResult::forbidden();
    return $result->addCacheableDependency($this->config('mukurtu_search.settings'));
  }

  /**
   * Returns suggested items for the typed text.
   */
  public function suggest(Request $request): JsonResponse {
    $page = $this->page();
    $search = $this->search();
    $keys = trim((string) $request->query->get('q', ''));
    if (!$page || !$search || mb_strlen($keys) < (int) $search->getOption('min_length')) {
      return new JsonResponse(['groups' => [], 'all_url' => NULL]);
    }

    $all_url = Url::fromRoute($page['route'], [], ['query' => ['search_api_fulltext' => $keys]]);
    try {
      $query = $search->createQuery($keys, [
        'display' => $page['display'],
        'filter' => 'search_api_fulltext',
      ]);
      $groups = $this->builder->build($query, $keys, $page['link_groups']);
    }
    catch (SearchApiException | SearchApiAutocompleteException $e) {
      // Suggestions are a convenience; the search itself still works.
      $this->getLogger('mukurtu_browse')->error('Header search suggestions failed: @message', ['@message' => $e->getMessage()]);
      $groups = [];
    }

    // Not cached: results depend on the visitor's protocol access.
    return new JsonResponse([
      'groups' => $groups,
      'all_url' => $all_url->toString(TRUE)->getGeneratedUrl(),
    ]);
  }

}
