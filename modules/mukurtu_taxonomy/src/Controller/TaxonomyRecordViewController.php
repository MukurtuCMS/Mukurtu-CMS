<?php

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\taxonomy\TermInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Entity\EntityInterface;

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

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, EntityFieldManagerInterface $entity_field_manager, LanguageManagerInterface $language_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Display the taxonomy term page.
   */
  public function build(TermInterface $taxonomy_term) {
    $build = [];
    $allRecords = $this->getTaxonomyTermRecords($taxonomy_term);
    $results = $this->referencedContent($taxonomy_term);

    // Load the entities so we can render them.
    $entities = !empty($results) ? $this->entityTypeManager->getStorage('node')->loadMultiple($results) : [];
    $entityViewBuilder = $this->entityTypeManager->getViewBuilder('node');
    $langcode = $this->languageManager->getCurrentLanguage()->getId();

    // Render any records.
    $records = [];
    foreach ($allRecords as $record) {
      $records[] = [
        'id' => $record->id(),
        'tabid' => "record-{$record->id()}",
        'communities' => $this->getCommunitiesLabel($record),
        'title' => $record->getTitle(),
        'content' => $entityViewBuilder->view($record, 'taxonomy_record', $langcode),
      ];
    }

    // Render the referenced entities.
    $referencedContent = [];
    if (!empty($entities)) {
      foreach ($entities as $entity) {
        $referencedContent[] = $entityViewBuilder->view($entity, 'teaser', $langcode);
      }
      $referencedContent[] = [
        '#type' => 'pager',
      ];
    }

    $build['records'] = [
      '#theme' => 'taxonomy_records',
      '#active' => 1,
      '#records' => $records,
      '#referenced_content' => $referencedContent,
      '#attached' => [
        'library' => [
          'field_group/element.horizontal_tabs',
          'mukurtu_community_records/community-records'
        ],
      ],
    ];

    return $build;
  }

  /**
   * Build the communities label.
   */
  protected function getCommunitiesLabel(EntityInterface $node) {
    $communities = $node->get('field_communities')->referencedEntities();

    $communityLabels = [];
    foreach ($communities as $community) {
      // Skip any communities the user can't see.
      if (!$community->access('view', $this->currentUser)) {
        continue;
      }
      // @todo ordering?
      $communityLabels[] = $community->getName();
    }
    return implode(', ', $communityLabels);
  }

  /**
   * Return content with the taxonomy record relationship for this term.
   */
  protected function getTaxonomyTermRecords(TermInterface $taxonomy_term) {
    $config = \Drupal::config('mukurtu_taxonomy.settings');
    $enabledVocabs = $config->get('enabled_vocabularies') ?? [];

    // If the term vocabulary is not enabled for taxonomy records, return
    // an empty array.
    if (!in_array($taxonomy_term->bundle(), $enabledVocabs)) {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('field_representative_terms', $taxonomy_term->id());
    $query->condition('status', 1, '=');
    $query->accessCheck(TRUE);
    $results = $query->execute();
    return empty($results) ? [] : $this->entityTypeManager->getStorage('node')->loadMultiple($results);
  }

  /**
   * Return an array of content IDs for content that references the term or
   * any of its taxonomy records.
   */
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
    $contentQuery = $this->entityTypeManager->getStorage('node')->getQuery();
    $fieldConditions = count($searchFields) == 1 ? $contentQuery->andConditionGroup() : $contentQuery->orConditionGroup();

    // Add all the field conditions.
    foreach ($searchFields as $fieldCondition) {
      $fieldConditions->condition($fieldCondition['fieldname'], $fieldCondition['value'], $fieldCondition['operator']);
    }
    $contentQuery->condition($fieldConditions);

    // Published only.
    $contentQuery->condition('status', 1, '=');

    // Page the results.
    $contentQuery->pager(10);

    // Respect access.
    $contentQuery->accessCheck(TRUE);
    return $contentQuery->execute();
  }

}
