<?php

declare(strict_types=1);

namespace Drupal\mukurtu_search\Plugin\search_api\processor;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\search_api\Attribute\SearchApiProcessor;
use Drupal\search_api\Datasource\DatasourceInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\search_api\Processor\ProcessorProperty;
use Drupal\search_api\SearchApiException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Rolls every taxonomy reference field into two shared, index-wide fields.
 *
 * `search_api_db` creates one database index (key) per indexed field on the
 * denormalized index table, and MySQL/MariaDB cap a table at 64 keys. Adding a
 * per-field `__name__text` / `__uuid` variant for every taxonomy reference
 * field on every node bundle (Place, Person, DigitalHeritage, ...) blows past
 * that limit as soon as a site defines a handful of content-type-specific
 * vocabularies.
 *
 * This processor instead exposes:
 * - `all_taxonomy_term_names` - a fulltext field containing the names of every
 *   referenced taxonomy term, resolved for the item's language.
 * - `all_taxonomy_term_uuids` - a string field containing the UUID of every
 *   referenced taxonomy term.
 *
 * New content types and new taxonomy reference fields are picked up
 * automatically with no index config change and, crucially, no new database
 * key.
 */
#[SearchApiProcessor(
  id: 'taxonomy_term_aggregates',
  label: new TranslatableMarkup('Taxonomy term aggregates'),
  description: new TranslatableMarkup('Adds index-wide fields containing the names and UUIDs of every referenced taxonomy term, so new taxonomy reference fields are searchable without adding a database index per field.'),
  stages: [
    'add_properties' => 0,
  ],
)]
class TaxonomyTermAggregates extends ProcessorPluginBase {

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $processor->setEntityRepository($container->get('entity.repository'));
    return $processor;
  }

  /**
   * Sets the entity repository.
   *
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository.
   *
   * @return $this
   */
  public function setEntityRepository(EntityRepositoryInterface $entity_repository) {
    $this->entityRepository = $entity_repository;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public static function supportsIndex(IndexInterface $index) {
    foreach ($index->getDatasources() as $datasource) {
      if ($datasource->getEntityTypeId() === 'node') {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getPropertyDefinitions(?DatasourceInterface $datasource = NULL) {
    $properties = [];

    // Index-wide (processor-derived) properties, not tied to a datasource.
    if (!$datasource) {
      $properties['all_taxonomy_term_names'] = new ProcessorProperty([
        'label' => $this->t('All taxonomy term names'),
        'description' => $this->t('Names of every referenced taxonomy term, for fulltext search.'),
        'type' => 'text',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]);

      $properties['all_taxonomy_term_uuids'] = new ProcessorProperty([
        'label' => $this->t('All taxonomy term UUIDs'),
        'description' => $this->t('UUID of every referenced taxonomy term.'),
        'type' => 'string',
        'processor_id' => $this->getPluginId(),
        'is_list' => TRUE,
      ]);
    }

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function addFieldValues(ItemInterface $item) {
    try {
      $entity = $item->getOriginalObject()->getValue();
    }
    catch (SearchApiException) {
      return;
    }

    if (!$entity instanceof ContentEntityInterface) {
      return;
    }

    $name_fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'all_taxonomy_term_names');
    $uuid_fields = $this->getFieldsHelper()
      ->filterForPropertyPath($item->getFields(), NULL, 'all_taxonomy_term_uuids');

    if (!$name_fields && !$uuid_fields) {
      return;
    }

    $langcode = $item->getLanguage();
    $names = [];
    $uuids = [];

    foreach ($entity->getFieldDefinitions() as $field_name => $definition) {
      if ($definition->getType() !== 'entity_reference' || $definition->getSetting('target_type') !== 'taxonomy_term') {
        continue;
      }

      $field_items = $entity->get($field_name);
      if ($field_items->isEmpty() || !$field_items instanceof EntityReferenceFieldItemListInterface) {
        continue;
      }

      foreach ($field_items->referencedEntities() as $term) {
        $uuids[$term->uuid()] = $term->uuid();
        // Resolve the term in the item's language so a translated index row
        // gets the translated term name (see the multilingual rules in
        // CLAUDE.md - read labels through a translation-context lookup, not
        // ->label() on the raw loaded entity).
        $translated = $this->entityRepository->getTranslationFromContext($term, $langcode);
        $label = trim((string) $translated->label());
        if ($label !== '') {
          $names[$label] = $label;
        }
      }
    }

    foreach ($name_fields as $field) {
      foreach ($names as $name) {
        $field->addValue($name);
      }
    }
    foreach ($uuid_fields as $field) {
      foreach ($uuids as $uuid) {
        $field->addValue($uuid);
      }
    }
  }

}
