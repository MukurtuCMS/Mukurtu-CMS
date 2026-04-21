<?php

declare(strict_types=1);

namespace Drupal\search_api\Plugin\search_api\datasource;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Task\TaskManagerInterface;
use Drupal\search_api\Utility\Utility;

/**
 * Provides hook implementations on behalf of the Content Entity datasource.
 *
 * @see \Drupal\search_api\Plugin\search_api\datasource\ContentEntity
 */
class ContentEntityTrackingManager {

  /**
   * The base ID of the datasources handled by this class.
   *
   * Can be overridden by subclasses to provide support for related datasources.
   */
  protected const DATASOURCE_BASE_ID = 'entity';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LanguageManagerInterface $languageManager,
    protected TaskManagerInterface $taskManager,
  ) {}

  /**
   * Computes the item ID for the given entity and language.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string|int $entity_id
   *   The entity ID.
   * @param string $langcode
   *   The language ID of the entity.
   *
   * @return string
   *   The datasource-specific item ID.
   */
  public static function formatItemId(string $entity_type, string|int $entity_id, string $langcode): string {
    return ContentEntity::formatItemId($entity_type, $entity_id, $langcode);
  }

  /**
   * Implements hook_entity_insert().
   *
   * Adds entries for all languages of the new entity to the tracking table for
   * each index that tracks entities of this type.
   *
   * By setting the $entity->search_api_skip_tracking property to a true-like
   * value before this hook is invoked, you can prevent this behavior and make the
   * Search API ignore this new entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The new entity.
   *
   * @see search_api_entity_insert()
   */
  public function entityInsert(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface) {
      $this->trackEntityChange($entity, TRUE);
    }
  }

  /**
   * Implements hook_entity_update().
   *
   * Updates the corresponding tracking table entries for each index that tracks
   * this entity.
   *
   * Also takes care of new or deleted translations.
   *
   * By setting the $entity->search_api_skip_tracking property to a true-like
   * value before this hook is invoked, you can prevent this behavior and make the
   * Search API ignore this update.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The updated entity.
   *
   * @see search_api_entity_update()
   */
  public function entityUpdate(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface) {
      $this->trackEntityChange($entity);
    }
  }

  /**
   * Queues an entity for indexing.
   *
   * If "Index items immediately" is enabled for the index, the entity will be
   * indexed right at the end of the page request.
   *
   * When calling this method with an existing entity
   * (@code $new = FALSE @endcode), changes in the existing translations will
   * only be recognized if an appropriate @code $entity->original @endcode value
   * is set.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to be indexed.
   * @param bool $new
   *   (optional) TRUE if this is a new entity, FALSE if it already existed (and
   *   should already be known to the tracker).
   */
  public function trackEntityChange(ContentEntityInterface $entity, bool $new = FALSE) {
    // Check if the entity is a content entity.
    if (!empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    // Compare old and new languages for the entity to identify inserted,
    // updated and deleted translations (and, therefore, search items).
    $entity_id = $entity->id();
    $new_translations = array_keys($entity->getTranslationLanguages());
    $old_translations = [];
    if (!$new) {
      // In case we don't have the original, fall back to the current entity,
      // and assume no new translations were added.
      $original = DeprecationHelper::backwardsCompatibleCall(
        \Drupal::VERSION,
        '11.2',
        fn () => $entity->getOriginal() ?: $entity,
        fn () => $entity->original ?? $entity,
      );
      $old_translations = array_keys($original->getTranslationLanguages());
    }
    $deleted_translations = array_diff($old_translations, $new_translations);
    $inserted_translations = array_diff($new_translations, $old_translations);
    $updated_translations = array_diff($new_translations, $inserted_translations);

    $datasource_id = static::DATASOURCE_BASE_ID . ':' . $entity->getEntityTypeId();
    $get_ids = function (string $langcode) use ($entity): string {
      return static::formatItemId($entity->getEntityTypeId(), $entity->id(), $langcode);
    };
    $inserted_ids = array_map($get_ids, $inserted_translations);
    $updated_ids = array_map($get_ids, $updated_translations);
    $deleted_ids = array_map($get_ids, $deleted_translations);

    foreach ($indexes as $index) {
      if ($inserted_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $inserted_ids);
        if ($filtered_item_ids) {
          $index->trackItemsInserted($datasource_id, $filtered_item_ids);
        }
      }
      if ($updated_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $updated_ids);
        if ($filtered_item_ids) {
          $index->trackItemsUpdated($datasource_id, $filtered_item_ids);
        }
      }
      if ($deleted_ids) {
        $filtered_item_ids = static::filterValidItemIds($index, $datasource_id, $deleted_ids);
        if ($filtered_item_ids) {
          $index->trackItemsDeleted($datasource_id, $filtered_item_ids);
        }
      }
    }
  }

  /**
   * Implements hook_entity_delete().
   *
   * Deletes all entries for this entity from the tracking table for each index
   * that tracks this entity type.
   *
   * By setting the $entity->search_api_skip_tracking property to a true-like
   * value before this hook is invoked, you can prevent this behavior and make the
   * Search API ignore this deletion. (Note that this might lead to stale data in
   * the tracking table or on the server, since the item will not removed from
   * there (if it has been added before).)
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The deleted entity.
   *
   * @see search_api_entity_delete()
   */
  public function entityDelete(EntityInterface $entity) {
    // Check if the entity is a content entity.
    if (!($entity instanceof ContentEntityInterface)
        || !empty($entity->search_api_skip_tracking)) {
      return;
    }

    $indexes = $this->getIndexesForEntity($entity);
    if (!$indexes) {
      return;
    }

    // Remove the search items for all the entity's translations.
    $item_ids = [];
    $entity_id = $entity->id();
    foreach (array_keys($entity->getTranslationLanguages()) as $langcode) {
      $item_ids[] = static::formatItemId($entity->getEntityTypeId(), $entity_id, $langcode);
    }
    $datasource_id = static::DATASOURCE_BASE_ID . ':' . $entity->getEntityTypeId();
    foreach ($indexes as $index) {
      $index->trackItemsDeleted($datasource_id, $item_ids);
    }
  }

  /**
   * Retrieves all indexes that are configured to index the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity for which to check.
   *
   * @return \Drupal\search_api\IndexInterface[]
   *   All indexes that are configured to index the given entity (using the
   *   default Content Entity datasource plugin).
   */
  public function getIndexesForEntity(ContentEntityInterface $entity): array {
    // @todo This is called for every single entity insert, update or deletion
    //   on the whole site. Should maybe be cached?
    $datasource_id = static::DATASOURCE_BASE_ID . ':' . $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $has_bundles = $entity->getEntityType()->hasKey('bundle');

    /** @var \Drupal\search_api\IndexInterface[] $indexes */
    $indexes = [];
    try {
      $indexes = $this->entityTypeManager->getStorage('search_api_index')
        ->loadMultiple();
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException) {
      // Can't really happen, but play it safe to appease static code analysis.
    }

    foreach ($indexes as $index_id => $index) {
      // Filter out indexes that don't contain the datasource in question.
      if (!$index->isValidDatasource($datasource_id)) {
        unset($indexes[$index_id]);
      }
      elseif ($has_bundles) {
        // If the entity type supports bundles, we also have to filter out
        // indexes that exclude the entity's bundle.
        try {
          $config = $index->getDatasource($datasource_id)->getConfiguration();
        }
        catch (SearchApiException) {
          // Can't really happen, but play it safe to appease static code
          // analysis.
          unset($indexes[$index_id]);
          continue;
        }
        if (!Utility::matches($entity_bundle, $config['bundles'])) {
          unset($indexes[$index_id]);
        }
      }
    }

    return $indexes;
  }

  /**
   * Implements hook_ENTITY_TYPE_update() for type "search_api_index".
   *
   * Detects changes in the selected bundles or languages and adds/removes items
   * to/from tracking accordingly.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that was updated.
   *
   * @see search_api_search_api_index_update()
   */
  public function indexUpdate(IndexInterface $index) {
    if (!$index->status()) {
      return;
    }
    /** @var \Drupal\search_api\IndexInterface $original */
    $original = DeprecationHelper::backwardsCompatibleCall(
      \Drupal::VERSION,
      '11.2',
      fn () => $index->getOriginal(),
      fn () => $index->original ?? NULL,
    );
    if (!$original || !$original->status()) {
      return;
    }

    foreach ($index->getDatasources() as $datasource_id => $datasource) {
      if (
        $datasource->getBaseId() != static::DATASOURCE_BASE_ID
        || !$original->isValidDatasource($datasource_id)
      ) {
        continue;
      }
      $old_datasource = $original->getDatasourceIfAvailable($datasource_id);
      $old_config = $old_datasource->getConfiguration();
      $new_config = $datasource->getConfiguration();

      if ($old_config != $new_config) {
        // Bundles and languages share the same structure, so changes can be
        // processed in a unified way.
        $tasks = [];
        $insert_task = ContentEntityTaskManager::INSERT_ITEMS_TASK_TYPE;
        $delete_task = ContentEntityTaskManager::DELETE_ITEMS_TASK_TYPE;
        $settings = [];
        try {
          $entity_type = $this->entityTypeManager
            ->getDefinition($datasource->getEntityTypeId());
          if ($entity_type->hasKey('bundle')) {
            $settings['bundles'] = $datasource->getBundles();
          }
          if ($entity_type->isTranslatable()) {
            $settings['languages'] = $this->languageManager->getLanguages();
          }
        }
        catch (PluginNotFoundException) {
          // Ignore.
        }

        // Determine which bundles/languages have been newly selected or
        // deselected and then assign them to the appropriate actions depending
        // on the current "default" setting.
        foreach ($settings as $setting => $all) {
          $old_selected = array_flip($old_config[$setting]['selected']);
          $new_selected = array_flip($new_config[$setting]['selected']);

          // First, check if the "default" setting changed and invert the checked
          // items for the old config, so the following comparison makes sense.
          if ($old_config[$setting]['default'] != $new_config[$setting]['default']) {
            $old_selected = array_diff_key($all, $old_selected);
          }

          $newly_selected = array_keys(array_diff_key($new_selected, $old_selected));
          $newly_unselected = array_keys(array_diff_key($old_selected, $new_selected));
          if ($new_config[$setting]['default']) {
            $tasks[$insert_task][$setting] = $newly_unselected;
            $tasks[$delete_task][$setting] = $newly_selected;
          }
          else {
            $tasks[$insert_task][$setting] = $newly_selected;
            $tasks[$delete_task][$setting] = $newly_unselected;
          }
        }

        // This will keep only those tasks where at least one of "bundles" or
        // "languages" is non-empty.
        $tasks = array_filter($tasks, 'array_filter');
        foreach ($tasks as $task => $data) {
          $data += [
            'datasource' => $datasource_id,
            'page' => 0,
          ];
          $this->taskManager->addTask($task, NULL, $index, $data);
        }

        // If we added any new tasks, set a batch for them. (If we aren't in a
        // form submission, this will just be ignored.)
        if ($tasks) {
          $this->taskManager->setTasksBatch([
            'index_id' => $index->id(),
            'type' => array_keys($tasks),
          ]);
        }
      }
    }
  }

  /**
   * Filters a set of datasource-specific item IDs.
   *
   * Returns only those item IDs that are valid for the given datasource and
   * index. This method only checks the item language, though – whether an
   * entity with that ID actually exists, or whether it has a bundle included
   * for that datasource, is not verified.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which to validate.
   * @param string $datasource_id
   *   The ID of the datasource on the index for which to validate.
   * @param string[] $item_ids
   *   The item IDs to be validated.
   *
   * @return string[]
   *   All given item IDs that are valid for that index and datasource.
   */
  public static function filterValidItemIds(IndexInterface $index, string $datasource_id, array $item_ids): array {
    if (!$index->isValidDatasource($datasource_id)) {
      return $item_ids;
    }

    try {
      $config = $index->getDatasource($datasource_id)->getConfiguration();
    }
    catch (SearchApiException) {
      // Can't really happen, but play it safe to appease static code analysis.
      return $item_ids;
    }

    // If the entity type doesn't allow translations, we just accept all IDs.
    // (If the entity type were translatable, the config key would have been set
    // with the default configuration.)
    if (!isset($config['languages']['selected'])) {
      return $item_ids;
    }
    $always_valid = [
      LanguageInterface::LANGCODE_NOT_SPECIFIED,
      LanguageInterface::LANGCODE_NOT_APPLICABLE,
    ];
    $valid_ids = [];
    foreach ($item_ids as $item_id) {
      $pos = strrpos($item_id, ':');
      // Item IDs without colons are always invalid.
      if ($pos === FALSE) {
        continue;
      }
      $langcode = substr($item_id, $pos + 1);
      if (Utility::matches($langcode, $config['languages'])
          || in_array($langcode, $always_valid)) {
        $valid_ids[] = $item_id;
      }
    }
    return $valid_ids;
  }

}
