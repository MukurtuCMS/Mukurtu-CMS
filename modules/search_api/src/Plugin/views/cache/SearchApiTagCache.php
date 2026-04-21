<?php

namespace Drupal\search_api\Plugin\views\cache;

use Drupal\views\Attribute\ViewsCache;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\views\Plugin\views\cache\Tag;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines a tag-based cache plugin for use with Search API views.
 *
 * This cache plugin basically sets an unlimited cache life time for the view,
 * but the view will be refreshed when any of its cache tags are invalidated.
 *
 * Use this for search results views that are fully controlled by a single
 * Drupal instance. A common use case is a website that uses the default
 * database search backend and does not index any external datasources.
 *
 * @ingroup views_cache_plugins
 */
#[ViewsCache(
  id: 'search_api_tag',
  title: new TranslatableMarkup('Search API (tag-based)'),
  help: new TranslatableMarkup('Cache results until the associated cache tags are invalidated. Useful for small sites that use the database search backend. <strong>Caution:</strong> Can lead to stale results and might harm performance for complex search pages.'),
)]
class SearchApiTagCache extends Tag {

  use SearchApiCachePluginTrait;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|null
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $cache */
    $cache = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    $cache->setEntityTypeManager($container->get('entity_type.manager'));

    return $cache;
  }

  /**
   * Retrieves the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  public function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::service('entity_type.manager');
  }

  /**
   * Sets the entity type manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The new entity type manager.
   *
   * @return $this
   */
  public function setEntityTypeManager(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRowId(ResultRow $row) {
    return $row->search_api_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRowCacheTags(ResultRow $row) {
    $tags = [];

    foreach ($row->_relationship_objects as $objects) {
      /** @var \Drupal\Core\TypedData\ComplexDataInterface $object */
      foreach ($objects as $object) {
        $entity = $object->getValue();
        if ($entity instanceof EntityInterface) {
          $tags = Cache::mergeTags($tags, $entity->getCacheTags());
        }
      }
    }

    return $tags;
  }

}
