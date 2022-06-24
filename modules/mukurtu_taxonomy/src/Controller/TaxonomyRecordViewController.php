<?php

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\taxonomy\TermInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;

class TaxonomyRecordViewController implements ContainerInjectionInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * The language manager service.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('entity_field.manager'),
      $container->get('language_manager'),
    );
  }

  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->languageManager = $language_manager;
  }

  public function build(TermInterface $taxonomy_term) {
    $build = [];
    $results = $this->referencedContent($taxonomy_term);

    // Load the entities so we can render them.
    $entities = !empty($results) ? $this->entityTypeManager->getStorage('node')->loadMultiple($results) : [];

    // Render the entities.
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    $entityViewBuilder = $this->entityTypeManager->getViewBuilder('node');
    foreach ($entities as $entity) {
      $build['reference_fields']['results'][] = $entityViewBuilder->view($entity, 'teaser', $langcode);
    }
    $build['reference_fields']['results'][] = [
      '#type' => 'pager',
    ];

    return $build;
  }

  protected function getTaxonomyTermRecords(TermInterface $taxonomy_term) {
    return $this->entityTypeManager->getStorage('node')->loadMultiple([1]);
  }

  protected function referencedContent(TermInterface $taxonomy_term) {
    // Get any taxonomy records associated with this term.
    $records = $this->getTaxonomyTermRecords($taxonomy_term);

    // Get all field definitions for nodes.
    $fields = $this->entityFieldManager->getActiveFieldStorageDefinitions('node');

    // Build a list of all the fields we should be searching.
    $searchFields = [];

    // Entity Reference Fields.
    foreach ($fields as $fieldname => $field) {
      if ($field->gettype() == 'entity_reference'){
        // Find all entity reference field references to this taxonomy term.
        if ($field->getSetting('target_type') == 'taxonomy_term') {
          $searchFields[] = ['fieldname' => $fieldname, 'value' => $taxonomy_term->id(), 'operator' => NULL];
        }
        // Find all entity reference field references to any associated taxonomy records.
        if ($field->getSetting('target_type') == 'node') {
          foreach ($records as $record) {
            $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->id(), 'operator' => NULL];
          }
        }
      }
    }

    // Text Fields that support embeds. Search for embeds of the record(s).
    if (count($records) > 0) {
      foreach ($fields as $fieldname => $field) {
        if (in_array($field->gettype(), ['text', 'text_long', 'text_with_summary'])) {
          foreach ($records as $record) {
            $searchFields[] = ['fieldname' => $fieldname, 'value' => $record->uuid(), 'operator' => 'CONTAINS'];
          }
        }
      }
    }

    // Return early if there are no supported fields to query.
    if (count($searchFields) == 0) {
      return [];
    }

    // Query all those fields for references to the given media.
    $conjunction = count($searchFields) == 1 ? 'AND' : 'OR';
    $contentQuery = $this->entityTypeManager->getStorage('node')->getQuery($conjunction);

    // Add all the field conditions.
    foreach ($searchFields as $fieldCondition) {
      $contentQuery->condition($fieldCondition['fieldname'], $fieldCondition['value'], $fieldCondition['operator']);
    }

    // Page the results.
    $contentQuery->pager(10);

    // Respect access.
    $contentQuery->accessCheck(TRUE);
    return $contentQuery->execute();
  }

}
