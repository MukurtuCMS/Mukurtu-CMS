<?php

namespace Drupal\mukurtu_community_records;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Theme\Registry;
use Drupal\node\NodeInterface;
use Drupal\node\NodeViewBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;


class CommunityRecordNodeViewBuilder extends NodeViewBuilder {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new EntityViewBuilder.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\Core\Theme\Registry $theme_registry
   *   The theme registry.
   * @param \Drupal\Core\Entity\EntityDisplayRepositoryInterface $entity_display_repository
   *   The entity display repository.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityRepositoryInterface $entity_repository, LanguageManagerInterface $language_manager, Registry $theme_registry, EntityDisplayRepositoryInterface $entity_display_repository, EntityTypeManagerInterface $entity_type_manager, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type, $entity_repository, $language_manager, $theme_registry, $entity_display_repository);
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity.repository'),
      $container->get('language_manager'),
      $container->get('theme.registry'),
      $container->get('entity_display.repository'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
    );
  }

  /**
   * Checks if an entity supports community records.
   *
   * @param EntityInterface $entity
   *   The entity.
   *
   * @return boolean
   *   TRUE if the entity supports community records.
   */
  protected function supportsCommunityRecords(EntityInterface $entity): bool {
    // Only nodes support community records currently.
    if ($entity->getEntityTypeId() !== 'node') {
      return FALSE;
    }

    // Check if the node has the required field to support community records.
    /** @var \Drupal\node\NodeInterface $entity */
    if ($entity->hasField('field_mukurtu_original_record')) {
      return TRUE;
    }

    // Check if the node bundle is enabled for community records.
    $config = $this->configFactory->get('mukurtu_community_records.settings');
    $allowedBundles = $config->get('allowed_community_record_bundles');
    if (!in_array($entity->bundle(), $allowedBundles)) {
      return FALSE;
    }

    return FALSE;
  }

  /**
   * Sort the input arrays defined in sortRecords by weight.
   */
  private function weightSort($inputs, $weights) {
    // If we have no more inputs we are done.
    if (empty($inputs)) {
      return [];
    }

    // If we have no more community weights to evaluate, datesort the remaining
    // inputs.
    if (empty($weights)) {
      return $this->dateSort($inputs);
    }

    // Grab the next community by weight.
    $currentWeightKey = array_key_first($weights);

    // Slice off the rest of the community weights.
    $newWeights = array_slice($weights, 1, NULL, TRUE);

    // Pull out any records that contain the current community.
    $sorted = [];
    $unsorted = [];
    foreach ($inputs as $input) {
      if (in_array($currentWeightKey, $input['communities'])) {
        $sorted[] = $input;
      } else {
        $unsorted[] = $input;
      }
    }

    // Keep sorting!
    return array_merge($this->dateSort($sorted), $this->weightSort($unsorted, $newWeights));
  }

  /**
   * Sort the input arrays defined in sortRecords by creation date.
   */
  private function dateSort($inputs) {
    $dates = array_column($inputs, 'created');
    asort($dates);
    $sortedInputs = [];
    foreach ($dates as $key => $date) {
      $sortedInputs[] = $inputs[$key];
    }

    return $sortedInputs;
  }

  /**
   * Sort the records.
   */
  protected function sortRecords($records){
    $config = $this->configFactory->get('mukurtu_community_records.settings');
    $weights = $config->get('community_record_weights');

    // Dump the entities into a lighter weight structure to pass around.
    // This might be unneeded, maybe PHP is smart?
    $inputs = [];
    foreach ($records as $record) {
      $inputs[] = [
        'id' => $record->id(),
        'communities' => array_column($record->get('field_communities')->getValue(), 'target_id'),
        'created' => $record->getCreatedTime(),
      ];
    }

    // Hand our lightweight structure off to the real sort function.
    $sorted = $this->weightSort($inputs, $weights);

    // Put the actual entities in the correct order.
    $sortedRecords = [];
    foreach ($sorted as $s) {
      $sortedRecords[] = $records[$s['id']];
    }

    return $sortedRecords;
  }

  /**
   * Find all records associated with a node.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node to find community records for.
   *
   * @return \Drupal\node\NodeInterface[]
   *   An array of community record nodes that are associated with the given
   *   node.
   */
  protected function getCommunityRecords(NodeInterface $node): array {
    // Find the original record.
    $original_record = $node->get('field_mukurtu_original_record')->referencedEntities()[0] ?? $node;

    // Check if the user can actually see the original record.
    $allRecords = [];
    if ($original_record->access('view')) {
      $allRecords = [$original_record->id() => $original_record];
    }

    // Find all the community records for the original record.
    // The entity query system takes care of access checks for us here.
    $query = $this->entityTypeManager->getStorage($node->getEntityTypeId())->getQuery()
      ->condition('field_mukurtu_original_record', $original_record->id())
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');
    $results = $query->execute();

    $records = [];
    if (!empty($results)) {
      $records = $this->entityTypeManager->getStorage($node->getEntityTypeId())->loadMultiple($results);
    }
    $allRecords = $allRecords + $records;

    return $this->sortRecords($allRecords);
  }

  /**
   * Build the communities label.
   */
  protected function getCommunitiesLabel(NodeInterface $node) {
    $communities = $node->get('field_communities')->referencedEntities();

    $communityLabels = [];
    foreach ($communities as $community) {
      // @todo ordering?
      $communityLabels[] = $community->getName();
    }
    return implode(', ', $communityLabels);
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $entity, $view_mode = 'full', $langcode = NULL) {
    if ($view_mode == 'full' && $this->supportsCommunityRecords($entity)) {
      $all_records = $this->getCommunityRecords($entity);

      // If we only have a single record, render normally.
      if (empty($all_records) || count($all_records) === 1) {
        return parent::view($entity, $view_mode, $langcode);
      }

      // Community record tab definitions.
      $records = [];
      foreach ($all_records as $record) {
        $records[] = [
          'id' => $record->id(),
          'tabid' => "record-{$record->id()}",
          'communities' => $this->getCommunitiesLabel($record),
          'title' => $record->getTitle(),
          'content' => parent::view($record, $view_mode),
          'is_cr' => mukurtu_community_records_is_community_record($record),
        ];
      }

      // Community Records template build array.
      $build['template'] = [
        '#theme' => 'community_records',
        '#active' => $entity->id(),
        '#records' => $records,
        '#attached' => [
          'library' => [
            'field_group/element.horizontal_tabs',
            'mukurtu_community_records/community-records'
          ],
        ],
      ];

      return $build;
    }

    return parent::view($entity, $view_mode, $langcode);
  }

}
