<?php

namespace Drupal\mukurtu_core\Service;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Template\Attribute;
use Drupal\mukurtu_core\Event\RelatedContentProvenanceEvent;
use Drupal\node\NodeInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Builds a grouped, filterable render array for field_all_related_content.
 *
 * Distinguishes manually-added related content (field_related_content) from
 * content auto-discovered via a taxonomy term in field_other_names /
 * field_other_place_names, and groups the latter by taxonomy vocabulary, so
 * the Person/Place preprocess hooks can render a filterable "Referenced
 * Content" section instead of a single flattened list.
 */
class RelatedContentGrouper {

  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ConfigFactoryInterface $configFactory,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * Builds the render array for the entity's referenced content.
   *
   * @param \Drupal\node\NodeInterface $entity
   *   The Person or Place node.
   *
   * @return array
   *   A '#theme' => 'mukurtu_related_content_grouped' render array.
   */
  public function build(NodeInterface $entity): array {
    $config = $this->configFactory->get('mukurtu.settings');
    $cache = CacheableMetadata::createFromObject($entity);
    $cache->addCacheableDependency($config);
    $cache->addCacheTags(['node_list']);

    $manualIds = [];
    if ($entity->hasField('field_related_content')) {
      foreach ($entity->get('field_related_content')->referencedEntities() as $manualEntity) {
        $manualIds[$manualEntity->id()] = TRUE;
      }
    }

    $allById = [];
    if ($entity->hasField('field_all_related_content')) {
      foreach ($entity->get('field_all_related_content')->referencedEntities() as $relatedEntity) {
        $allById[$relatedEntity->id()] = $relatedEntity;
      }
    }

    $autoById = array_diff_key($allById, $manualIds);

    $provenance = [];
    $hasOtherNames = $entity->hasField('field_other_names') || $entity->hasField('field_other_place_names');
    if ($autoById && $hasOtherNames) {
      $event = new RelatedContentProvenanceEvent($entity, $autoById);
      $event = $this->eventDispatcher->dispatch($event, RelatedContentProvenanceEvent::EVENT_NAME);
      $provenance = $event->provenance;
    }

    // Resolve labels only for vocabularies actually matched, so we never
    // show an empty filter button.
    $vocabIds = [];
    foreach ($provenance as $info) {
      foreach ($info['vocabularies'] as $vid) {
        $vocabIds[$vid] = $vid;
      }
    }

    $vocabularyLabels = [];
    if ($vocabIds) {
      $vocabularyStorage = $this->entityTypeManager->getStorage('taxonomy_vocabulary');
      foreach ($vocabularyStorage->loadMultiple(array_keys($vocabIds)) as $vid => $vocabulary) {
        $vocabularyLabels[$vid] = $vocabulary->label();
        $cache->addCacheableDependency($vocabulary);
      }
      asort($vocabularyLabels);
    }

    $viewBuilder = $this->entityTypeManager->getViewBuilder('node');
    $items = [];
    // Distinct real buckets present (manual + each vocabulary), used to
    // decide whether a filter bar is worth showing at all. 'other' matches
    // (direct node reference / embedded UUID, no vocabulary) never get their
    // own filter button, they only ever show up under "All".
    $bucketsPresent = [];

    foreach ($allById as $nid => $node) {
      $access = $node->access('view', NULL, TRUE);
      $cache->addCacheableDependency($access);
      if (!$access->isAllowed()) {
        continue;
      }

      $sources = [];
      $vocabularies = [];

      if (isset($manualIds[$nid])) {
        $sources[] = 'manual';
        $bucketsPresent['manual'] = TRUE;
      }

      if (isset($provenance[$nid])) {
        foreach ($provenance[$nid]['vocabularies'] as $vid) {
          if (isset($vocabularyLabels[$vid])) {
            $vocabularies[] = $vid;
            $bucketsPresent[$vid] = TRUE;
          }
        }
        if ($provenance[$nid]['other'] && !$vocabularies) {
          $sources[] = 'other';
        }
      }

      // Every non-manual item in $allById was discovered via the event
      // dispatch above; fall back to 'other' so it's never left untagged.
      if (!$sources && !$vocabularies) {
        $sources[] = 'other';
      }

      $items[] = [
        'content' => $viewBuilder->view($node, 'featured'),
        'attributes' => new Attribute([
          'data-source' => implode(' ', $sources),
          'data-vocabulary' => implode(' ', $vocabularies),
        ]),
      ];
    }

    $filters = [];
    if (count($bucketsPresent) > 1) {
      $filters[] = ['id' => 'all', 'label' => $this->t('All')];
      if (!empty($bucketsPresent['manual'])) {
        $filters[] = ['id' => 'manual', 'label' => $this->t('Related Content')];
      }
      foreach ($vocabularyLabels as $vid => $label) {
        if (!empty($bucketsPresent[$vid])) {
          $filters[] = ['id' => $vid, 'label' => $label];
        }
      }
    }

    $build = [
      '#theme' => 'mukurtu_related_content_grouped',
      '#items' => $items,
      '#filters' => $filters,
      '#has_filters' => count($filters) > 1,
    ];

    if ($build['#has_filters']) {
      $build['#attached']['library'][] = 'mukurtu_core/related-content-filter';
    }

    $cache->applyTo($build);

    return $build;
  }

}
