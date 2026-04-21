<?php

namespace Drupal\search_api\Datasource;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Language\Language;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\ComplexDataInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\search_api\Plugin\IndexPluginBase;
use Drupal\search_api\Utility\Utility;

/**
 * Defines a base class from which other datasources may extend.
 *
 * Plugins extending this class need to provide the plugin definition using the
 * \Drupal\search_api\Attribute\SearchApiDatasource attribute. These definitions
 * may be altered using the "search_api.gathering_data_sources" event.
 *
 * A complete plugin definition should be written as in this example:
 *
 * @code
 * #[SearchApiDatasource(
 *   id: 'my_datasource',
 *   label: new TranslatableMarkup('My datasource'),
 *   description: new TranslatableMarkup('Exposes my custom items as a datasource.'),
 * )]
 * @endcode
 *
 * @see \Drupal\search_api\Attribute\SearchApiDatasource
 * @see \Drupal\search_api\Datasource\DatasourcePluginManager
 * @see \Drupal\search_api\Datasource\DatasourceInterface
 * @see \Drupal\search_api\Event\SearchApiEvents::GATHERING_DATA_SOURCES
 * @see plugin_api
 */
abstract class DatasourcePluginBase extends IndexPluginBase implements DatasourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function load($id) {
    $items = $this->loadMultiple([$id]);
    return $items ? reset($items) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLabel(ComplexDataInterface $item) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemBundle(ComplexDataInterface $item) {
    return $this->getPluginId();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemLanguage(ComplexDataInterface $item) {
    if ($item instanceof TranslatableInterface) {
      return $item->language()->getId();
    }
    $item = $item->getValue();
    if ($item instanceof TranslatableInterface) {
      return $item->language()->getId();
    }
    return Language::LANGCODE_NOT_SPECIFIED;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemUrl(ComplexDataInterface $item) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function checkItemAccess(ComplexDataInterface $item, ?AccountInterface $account = NULL) {
    @trigger_error('\Drupal\search_api\Datasource\DatasourceInterface::checkItemAccess() is deprecated in search_api:8.x-1.14 and is removed from search_api:2.0.0. Use getItemAccessResult() instead. See https://www.drupal.org/node/3051902', E_USER_DEPRECATED);
    return $this->getItemAccessResult($item, $account)->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getItemAccessResult(ComplexDataInterface $item, ?AccountInterface $account = NULL) {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getViewModes($bundle = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getBundles() {
    return [
      $this->getPluginId() => $this->label(),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function viewItem(ComplexDataInterface $item, $view_mode, $langcode = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function viewMultipleItems(array $items, $view_mode, $langcode = NULL) {
    $build = [];
    foreach ($items as $key => $item) {
      $build[$key] = $this->viewItem($item, $view_mode, $langcode);
    }
    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId() {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getItemIds($page = NULL) {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function canContainEntityReferences(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getAffectedItemsForEntityChange(EntityInterface $entity, array $foreign_entity_relationship_map, ?EntityInterface $original_entity = NULL): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFieldDependencies(array $fields) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getListCacheContexts() {
    return [];
  }

  /**
   * Creates a combined item ID from a raw item ID.
   *
   * @param string $raw_item_id
   *   The raw (datasource-specific) item ID.
   *
   * @return string
   *   A combined ID, containing the datasource ID and the raw item ID to
   *   uniquely reference this item across the Search API.
   */
  protected function createCombinedId(string $raw_item_id): string {
    return Utility::createCombinedId($this->getPluginId(), $raw_item_id);
  }

}
