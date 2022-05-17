<?php

namespace Drupal\mukurtu_community_records\Controller;

use Drupal\Core\Entity\EntityInterface;
use Drupal\node\Controller\NodeViewController;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller to view Community Records.
 */
class CommunityRecordsViewController extends NodeViewController {

  /**
   * The configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * {@inheritDoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, EntityRepositoryInterface $entity_repository, ConfigFactoryInterface $config_factory) {
    parent::__construct($entity_type_manager, $renderer, $current_user, $entity_repository);
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('entity.repository'),
      $container->get('config.factory'),
    );
  }

  /**
   * Community Records Display.
   */
  public function view(EntityInterface $node, $view_mode = 'full', $langcode = NULL) {
    // Check if CRs are enabled site wide for this bundle.
    $crConfig = $this->configFactory->get('mukurtu_community_records.settings');
    $allowedBundles = $crConfig->get('allowed_community_record_bundles');
    if (!in_array($node->bundle(), $allowedBundles)) {
      // Not enabled for community records, failover to standard node view.
      return parent::view($node, $view_mode, $langcode);
    }

    // If this display mode isn't set to display community records,
    // fall back to the default node view controller.
    if (!$this->isRecordDisplayMode($node, $view_mode)) {
      return parent::view($node, $view_mode, $langcode);
    }

    // If CRs are broken or there are no CRs, fall back to the default node
    // view controller.
    $allRecords = $this->getAllRecords($node);
    if (empty($allRecords) || count($allRecords) == 1) {
      return parent::view($node, $view_mode, $langcode);
    }

    foreach ($allRecords as $record) {
      $records[] = [
        'id' => $record->id(),
        'tabid' => "record-{$record->id()}",
        'communities' => $this->getCommunitiesLabel($record),
        'title' => $record->getTitle(),
        'content' => parent::view($record, $view_mode),
      ];
    }

    $build['template'] = [
      '#theme' => 'community_records',
      '#active' => $node->id(),
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
   * Check if a display mode is configured for community records.
   *
   * @param \Drupal\Core\Entity\EntityInterface $node
   *   The node being displayed.
   * @param string $view_mode
   *   The requested view mode.
   *
   * @return boolean
   *   TRUE if configured to display community records.
   */
  protected function isRecordDisplayMode(EntityInterface $node, $view_mode) {
    return $view_mode === 'full';
  }

  /**
   * Find all records associated with a node.
   */
  protected function getAllRecords(EntityInterface $node) {
    // Doesn't support/not configured for community records,
    // so exit early and display the single node.
    if (!$node->hasField('field_mukurtu_original_record')) {
      return [$node];
    }

    // Find the original record.
    $original_record = $node->get('field_mukurtu_original_record')->referencedEntities()[0] ?? $node;

    // Check if the user can actually see the original record.
    if ($original_record->access('view', $this->currentUser)) {
      $allRecords = [$original_record->id() => $original_record];
    }

    // Find all the community records for the original record.
    // The entity query system takes care of access checks for us here.
    $query = $this->entityTypeManager->getStorage($node->getEntityTypeId())->getQuery()
      ->condition('field_mukurtu_original_record', $original_record->id())
      ->accessCheck(TRUE)
      ->sort('created', 'DESC');
    $results = $query->execute();

    $records = $this->entityTypeManager->getStorage($node->getEntityTypeId())->loadMultiple($results);
    $allRecords = array_merge($allRecords, $records);

    // @todo Ordering.
    return $allRecords;
  }

}
