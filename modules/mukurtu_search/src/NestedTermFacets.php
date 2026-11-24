<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\TypedData\EntityDataDefinitionInterface;
use Drupal\Core\Field\TypedData\FieldItemDataDefinitionInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\ListDataDefinitionInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Makes search facets on taxonomy term IDs nest (#2522).
 *
 * A facet can only nest when its values are term IDs: names aren't unique,
 * and the facets module looks up parents and children by ID. For every index
 * field that holds term IDs, Search API's "hierarchy" processor indexes each
 * term's ancestors too, so filtering on a parent finds content tagged with
 * any term nested under it. Facet filters on those fields get nesting and
 * translated labels.
 *
 * This runs whenever an index or view is saved, so facets a site adds later
 * nest too. Facets on term name fields are left as they are, flat.
 *
 * @see \Drupal\mukurtu_search\Hook\NestedTermFacetHooks
 */
class NestedTermFacets {

  /**
   * The Search API hierarchy processor setting for taxonomy terms.
   */
  const HIERARCHY_PROPERTY = 'taxonomy_term-parent';

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FieldsHelperInterface $fieldsHelper,
  ) {}

  /**
   * Gets the IDs of an index's fields that hold taxonomy term IDs.
   *
   * @return string[]
   *   Field IDs.
   */
  public function termIdFields(IndexInterface $index): array {
    return array_keys($this->termIdFieldVocabularies($index));
  }

  /**
   * Gets the vocabularies each of an index's term ID fields can reference.
   *
   * @return array
   *   Vocabulary IDs, keyed by field ID. Empty if any vocabulary is allowed.
   */
  public function termIdFieldVocabularies(IndexInterface $index): array {
    $fields = [];
    foreach ($index->getFields() as $field_id => $field) {
      try {
        $definition = $field->getDataDefinition();
      }
      catch (SearchApiException) {
        continue;
      }
      if ($definition instanceof ListDataDefinitionInterface) {
        $definition = $definition->getItemDefinition();
      }
      if (!$definition instanceof ComplexDataDefinitionInterface) {
        continue;
      }
      $reference = $definition->getPropertyDefinition('entity');
      if (!$reference instanceof DataReferenceDefinitionInterface) {
        continue;
      }
      $target = $reference->getTargetDefinition();
      if (!$target instanceof EntityDataDefinitionInterface || $target->getEntityTypeId() !== 'taxonomy_term') {
        continue;
      }
      $bundles = $definition instanceof FieldItemDataDefinitionInterface
        ? ($definition->getFieldDefinition()->getSetting('handler_settings')['target_bundles'] ?? [])
        : [];
      $fields[$field_id] = array_values($bundles ?: []);
    }
    return $fields;
  }

  /**
   * Makes the hierarchy processor index ancestors for term ID fields.
   *
   * Fields already set up, and other fields the processor covers, are kept.
   * The stored processor settings are changed directly: Index::preSave()
   * writes processor instances into them before presave hooks run, so a
   * processor added from a hook would otherwise not be saved.
   *
   * @return bool
   *   Whether the index changed.
   */
  public function applyToIndex(IndexInterface $index): bool {
    $field_ids = $this->termIdFields($index);
    if (!$field_ids) {
      return FALSE;
    }

    $settings = $index->get('processor_settings') ?? [];
    $fields = $settings['hierarchy']['fields'] ?? [];
    $missing = array_diff($field_ids, array_keys($fields));
    if (!$missing) {
      return FALSE;
    }

    $settings['hierarchy'] = [
      'weights' => $settings['hierarchy']['weights'] ?? ['preprocess_index' => -45],
      'fields' => $fields + array_fill_keys($missing, self::HIERARCHY_PROPERTY),
    ] + ($settings['hierarchy'] ?? []);
    $index->set('processor_settings', $settings);
    return TRUE;
  }

  /**
   * Makes facet filters on term ID fields nest and show translated labels.
   *
   * @return bool
   *   Whether the view changed.
   */
  public function applyToView(ViewEntityInterface $view): bool {
    $changed = FALSE;
    $term_fields = [];
    $displays = $view->get('display');
    foreach ($displays as $display_id => $display) {
      foreach ($display['display_options']['filters'] ?? [] as $filter_id => $filter) {
        if (($filter['plugin_id'] ?? NULL) !== 'facets_filter' || !str_starts_with((string) ($filter['table'] ?? ''), 'search_api_index_')) {
          continue;
        }
        $index_id = substr($filter['table'], strlen('search_api_index_'));
        if (!isset($term_fields[$index_id])) {
          $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
          $term_fields[$index_id] = $index ? $this->termIdFields($index) : [];
        }
        $field_id = substr((string) ($filter['field'] ?? ''), strlen('facets_'));
        if (!in_array($field_id, $term_fields[$index_id], TRUE)) {
          continue;
        }

        $updated = $this->nestFilter($filter);
        if ($updated !== $filter) {
          $displays[$display_id]['display_options']['filters'][$filter_id] = $updated;
          $changed = TRUE;
        }
      }
    }
    if ($changed) {
      $view->set('display', $displays);
    }
    return $changed;
  }

  /**
   * Gives one facet filter nesting and translated labels.
   *
   * The site's choice of expanding nested terms is kept.
   */
  protected function nestFilter(array $filter): array {
    $filter['hierarchy'] = TRUE;
    $filter['facet']['hierarchy'] = 'taxonomy';
    $filter['facet']['expand_hierarchy'] = $filter['facet']['expand_hierarchy'] ?? FALSE;
    $filter['facet']['processor_configs'] = ($filter['facet']['processor_configs'] ?? []) + [
      'translate_entity' => [
        'processor_id' => 'translate_entity',
        'weights' => ['build' => 5],
        'settings' => [],
      ],
      'hierarchy_processor' => [
        'processor_id' => 'hierarchy_processor',
        'weights' => ['build' => 100],
        'settings' => [],
      ],
    ];
    return $filter;
  }

  /**
   * Moves shipped term facets onto term ID fields, for update hooks.
   *
   * Adds the shipped term ID fields an index lacks, points the shipped facet
   * filters at them where they still use the old name fields, then reindexes
   * the index in batches. The presave hooks add the hierarchy processor and
   * the facet processors. Facets a site changed are left alone.
   *
   * @param array $sandbox
   *   The update hook's sandbox.
   * @param string $config_dir
   *   The module's config/install directory.
   * @param string $index_id
   *   The index ID.
   * @param string[] $field_ids
   *   The term ID fields to add, as shipped in the index's config.
   * @param array $filters
   *   For each view ID, the old and new field of each facet filter to switch,
   *   keyed by filter ID: [view_id => [filter_id => [old, new]]].
   *
   * @return int|null
   *   The number of items reindexed once finished, otherwise NULL.
   */
  public function updateShippedFacets(array &$sandbox, string $config_dir, string $index_id, array $field_ids, array $filters): ?int {
    /** @var \Drupal\search_api\IndexInterface|null $index */
    $index = $this->entityTypeManager->getStorage('search_api_index')->load($index_id);
    if (!$index || !$index->status()) {
      $sandbox['#finished'] = 1;
      return NULL;
    }

    if (!isset($sandbox['total'])) {
      $shipped = (new FileStorage($config_dir))->read("search_api.index.$index_id");
      foreach ($field_ids as $field_id) {
        if (!$index->getField($field_id) && isset($shipped['field_settings'][$field_id])) {
          $index->addField($this->fieldsHelper->createField($index, $field_id, $shipped['field_settings'][$field_id]));
        }
      }
      $index->save();

      $view_storage = $this->entityTypeManager->getStorage('view');
      foreach ($filters as $view_id => $view_filters) {
        $view = $view_storage->load($view_id);
        if (!$view) {
          continue;
        }
        $displays = $view->get('display');
        foreach ($view_filters as $filter_id => [$old, $new]) {
          $field = $displays['default']['display_options']['filters'][$filter_id]['field'] ?? NULL;
          if ($field === $old && $index->getField(substr($new, strlen('facets_')))) {
            $displays['default']['display_options']['filters'][$filter_id]['field'] = $new;
          }
        }
        $view->set('display', $displays)->save();
      }

      $index->reindex();
      $sandbox['total'] = $index->getTrackerInstance()->getRemainingItemsCount();
      $sandbox['indexed'] = 0;
    }

    if ($sandbox['total']) {
      $sandbox['indexed'] += $index->indexItems(50);
    }
    $remaining = $index->getTrackerInstance()->getRemainingItemsCount();
    $sandbox['#finished'] = ($remaining && $sandbox['total']) ? min(0.99, $sandbox['indexed'] / $sandbox['total']) : 1;
    return $sandbox['#finished'] === 1 ? $sandbox['indexed'] : NULL;
  }

}
