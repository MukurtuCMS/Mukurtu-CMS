<?php

namespace Drupal\redirect;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\Language;
use Drupal\redirect\Entity\Redirect;
use Drupal\redirect\Exception\RedirectLoopException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The redirect repository.
 */
class RedirectRepository {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * An array of found redirect IDs to avoid recursion.
   *
   * @var array
   */
  protected $foundRedirects = [];

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs a \Drupal\redirect\EventSubscriber\RedirectRequestSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity type manager.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack|null $request_stack
   *   The request stack.
   */
  public function __construct(EntityTypeManagerInterface $manager, Connection $connection, ConfigFactoryInterface $config_factory, ?RequestStack $request_stack = NULL) {
    $this->manager = $manager;
    $this->connection = $connection;
    $this->config = $config_factory->get('redirect.settings');
    if (!$request_stack) {
      @trigger_error('Calling ' . __METHOD__ . ' without the $request_stack argument is deprecated in redirect:1.11.0 and it will be required in redirect:2.0.0. See https://www.drupal.org/project/redirect/issues/3451531', E_USER_DEPRECATED);
      // @phpstan-ignore globalDrupalDependencyInjection.useDependencyInjection
      $request_stack = \Drupal::requestStack();
    }
    $this->requestStack = $request_stack;
  }

  /**
   * Gets a redirect for given path, query and language.
   *
   * @param string $source_path
   *   The redirect source path.
   * @param array $query
   *   The redirect source path query.
   * @param string $language
   *   The language for which is the redirect.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The matched redirect entity or NULL if no redirect was found.
   *
   * @throws \Drupal\redirect\Exception\RedirectLoopException
   */
  public function findMatchingRedirect($source_path, array $query = [], $language = Language::LANGCODE_NOT_SPECIFIED, ?CacheableMetadata $cacheable_metadata = NULL) {
    $source_path = ltrim($source_path, '/');
    $hashes = [Redirect::generateHash($source_path, $query, $language)];
    if ($language != Language::LANGCODE_NOT_SPECIFIED) {
      $hashes[] = Redirect::generateHash($source_path, $query, Language::LANGCODE_NOT_SPECIFIED);
    }

    // Add a hash without the query string if using passthrough_querystring.
    if (!empty($query) && $this->config->get('passthrough_querystring')) {
      $hashes[] = Redirect::generateHash($source_path, [], $language);
      if ($language != Language::LANGCODE_NOT_SPECIFIED) {
        $hashes[] = Redirect::generateHash($source_path, [], Language::LANGCODE_NOT_SPECIFIED);
      }
    }

    // Load redirects by hash. A direct query is used to improve performance.
    try {
      $rid = $this->connection->query('SELECT rid FROM {redirect} WHERE hash IN (:hashes[]) AND enabled = 1 ORDER BY LENGTH(redirect_source__query) DESC', [':hashes[]' => $hashes])->fetchField();
    }
    catch (\Exception) {
      // Return early in case the query failed. This can only happen if the
      // database update that adds the 'enabled' field hasn't run yet.
      return NULL;
    }

    if (!empty($rid)) {
      // Check if this is a loop.
      if (in_array($rid, $this->foundRedirects)) {
        throw new RedirectLoopException('/' . $source_path, $rid);
      }
      $this->foundRedirects[] = $rid;

      $redirect = $this->load($rid);
      if ($cacheable_metadata) {
        $cacheable_metadata->addCacheableDependency($redirect);
      }

      // Find chained redirects.
      if ($recursive = $this->findByRedirect($redirect, $language, $cacheable_metadata)) {
        // Reset found redirects.
        $this->foundRedirects = [];
        return $recursive;
      }

      return $redirect;
    }

    // Reset found redirects.
    $this->foundRedirects = [];
    return NULL;
  }

  /**
   * Helper function to find recursive redirects.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect object.
   * @param string $language
   *   The language to use.
   * @param \Drupal\Core\Cache\CacheableMetadata|null $cacheable_metadata
   *   The cacheable metadata for all the redirects involved.
   */
  protected function findByRedirect(Redirect $redirect, $language, ?CacheableMetadata $cacheable_metadata = NULL) {
    $uri = $redirect->getRedirectUrl();
    $base_url = $this->requestStack->getCurrentRequest()->getBaseUrl();
    $generated_url = $uri->toString(TRUE);
    $path = ltrim(substr($generated_url->getGeneratedUrl(), strlen($base_url)), '/');
    $query = $uri->getOption('query') ?: [];
    $return_value = $this->findMatchingRedirect($path, $query, $language, $cacheable_metadata);
    return $return_value ? $return_value->addCacheableDependency($generated_url) : $return_value;
  }

  /**
   * Finds redirects based on the source path.
   *
   * @param string $source_path
   *   The redirect source path (without the query).
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Array of redirect entities.
   */
  public function findBySourcePath($source_path) {
    $ids = $this->manager->getStorage('redirect')->getQuery()
      ->accessCheck(TRUE)
      ->condition('redirect_source.path', $source_path, 'LIKE')
      ->condition('enabled', 1)
      ->execute();
    return $this->manager->getStorage('redirect')->loadMultiple($ids);
  }

  /**
   * Finds redirects based on the destination URI.
   *
   * @param string[] $destination_uri
   *   List of destination URIs, for example ['internal:/node/123'].
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   Array of redirect entities.
   */
  public function findByDestinationUri(array $destination_uri) {
    $storage = $this->manager->getStorage('redirect');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('redirect_redirect.uri', $destination_uri, 'IN')
      ->condition('enabled', 1)
      ->execute();
    return $storage->loadMultiple($ids);
  }

  /**
   * Load redirect entity by id.
   *
   * @param int $redirect_id
   *   The redirect id.
   *
   * @return \Drupal\redirect\Entity\Redirect
   *   The redirect entity.
   */
  public function load($redirect_id) {
    return $this->manager->getStorage('redirect')->load($redirect_id);
  }

  /**
   * Loads multiple redirect entities.
   *
   * @param array $redirect_ids
   *   Redirect ids to load.
   *
   * @return \Drupal\redirect\Entity\Redirect[]
   *   List of redirect entities.
   */
  public function loadMultiple(?array $redirect_ids = NULL) {
    return $this->manager->getStorage('redirect')->loadMultiple($redirect_ids);
  }

}
