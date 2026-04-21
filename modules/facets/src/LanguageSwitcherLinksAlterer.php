<?php

namespace Drupal\facets;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\facets\UrlProcessor\UrlProcessorPluginManager;

/**
 * Helper service that alters the language switcher links.
 *
 * Facet URL aliases can be translated so we need to make sure that if we are
 * on a page that has facet filters in the URL, we replace the language switcher
 * links filters with the translated facet URL aliases for the target language.
 */
class LanguageSwitcherLinksAlterer {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cacheBackend;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The URL processor plugin manager.
   *
   * @var \Drupal\facets\UrlProcessor\UrlProcessorPluginManager
   */
  protected $urlProcessorManager;

  /**
   * The data needed to alter the language switcher links.
   *
   * @var array
   */
  protected $data = [];

  /**
   * LanguageSwitcherLinksAlterer constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   The cache backend.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\facets\UrlProcessor\UrlProcessorPluginManager $urlProcessorManager
   *   The URL processor plugin manager.
   */
  public function __construct(LanguageManagerInterface $languageManager, CacheBackendInterface $cacheBackend, EntityTypeManagerInterface $entityTypeManager, UrlProcessorPluginManager $urlProcessorManager) {
    $this->languageManager = $languageManager;
    $this->cacheBackend = $cacheBackend;
    $this->entityTypeManager = $entityTypeManager;
    $this->urlProcessorManager = $urlProcessorManager;
  }

  /**
   * Alters the language switcher links.
   *
   * @param array $links
   *   The links.
   * @param string $type
   *   The language type.
   * @param \Drupal\Core\Url $url
   *   The URL the switch links will be relative to.
   *
   * @see facets_language_switch_links_alter()
   */
  public function alter(array &$links, string $type, Url $url) {
    if (!$this->data) {
      $this->initializeData();
    }

    $current_language = $this->languageManager->getCurrentLanguage();

    foreach ($links as &$link) {
      if (empty($link['language']) || !($link['language'] instanceof LanguageInterface) || $link['language']->getId() === $current_language->getId()) {
        continue;
      }

      foreach ($this->data as $facet_info) {
        $filter_key = $facet_info['filter_key'];
        $separator = $facet_info['separator'];
        $url_aliases = $facet_info['url_aliases'];
        $original_language = $url_aliases['original'];

        if (!isset($link['query'][$filter_key]) || !is_array($link['query'][$filter_key])) {
          continue;
        }

        $untranslated_alias = $url_aliases[$this->languageManager->getCurrentLanguage()->getId()];
        $translated_alias = $url_aliases[$link['language']->getId()];

        // If we don't have a translated alias, that means we're trying to
        // create a link to the original language.
        if ($translated_alias === NULL) {
          $translated_alias = $url_aliases[$original_language];
        }
        // If we don't have an untranslated alias, we're trying to create a link
        // from the original language.
        if ($untranslated_alias === NULL) {
          $untranslated_alias = $url_aliases[$original_language];
        }

        foreach ($link['query'][$filter_key] as &$filters) {
          $filters = preg_replace(
            '/(' . $untranslated_alias . ")$separator/",
            $translated_alias . $separator,
            $filters
          );
        }
      }
    }
  }

  /**
   * Initializes the data needed for altering the language switcher links.
   *
   * It runs through all the facets on the site and all the languages and
   * creates a cache of the URL aliases for all the languages.
   */
  protected function initializeData() {
    $cache = $this->cacheBackend->get('facets_language_switcher_links');
    if ($cache) {
      $this->data = $cache->data;
      return;
    }

    $data = [];

    /** @var \Drupal\facets\FacetInterface[] $facets */
    $facets = $this->entityTypeManager->getStorage('facets_facet')->loadMultipleOverrideFree();

    $cache_tags = [];
    foreach ($facets as $facet) {
      $cache_tags = Cache::mergeTags($cache_tags, $facet->getCacheTags());

      /** @var \Drupal\facets\UrlProcessor\UrlProcessorInterface $urlProcessor */
      $id = $facet->getFacetSourceConfig()->getUrlProcessorName();
      $url_processor = $this->urlProcessorManager->createInstance($id, ['facet' => $facet]);

      if (!isset($data[$facet->id()])) {
        $data[$facet->id()] = [
          'separator' => $url_processor->getSeparator(),
          'filter_key' => $facet->getFacetSourceConfig()->getFilterKey(),
          'url_aliases' => [
            'original' => $facet->language()->getId(),
            $facet->language()->getId() => $facet->getUrlAlias(),
          ],
        ];
      }

      foreach ($this->languageManager->getLanguages() as $language) {
        if ($language->getId() === $facet->language()->getId()) {
          // Skip the original facet language as it's covered above.
          continue;
        }

        $config_name = 'facets.facet.' . $facet->id();
        $translated_alias = $this->languageManager->getLanguageConfigOverride($language->getId(), $config_name)->get('url_alias');
        $data[$facet->id()]['url_aliases'][$language->getId()] = $translated_alias;
      }
    }

    $this->data = $data;
    $this->cacheBackend->set('facets_language_switcher_links', $data, Cache::PERMANENT, $cache_tags);
  }

}
