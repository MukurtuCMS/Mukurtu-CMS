<?php

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

class TaxonomyRecordViewController extends ControllerBase implements ContainerInjectionInterface {

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

  protected $backend;

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
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName() {
    $views = [
      'db' => 'mukurtu_taxonomy_references',
      'solr' => 'mukurtu_taxonomy_references_solr',
    ];

    return $views[$this->backend];
  }

  /**
   * Return the facet source ID to use based on the search backend config.
   *
   * @return string
   *   The facet source ID.
   */
  protected function getFacetSourceId() {
    $views = [
      'db' => 'search_api:views_block__mukurtu_taxonomy_references__content_block',
      'solr' => 'search_api:views_block__mukurtu_taxonomy_references_solr__content_block',
    ];

    return $views[$this->backend];
  }

  /**
   * Display the taxonomy term page.
   */
  public function build(TermInterface $taxonomy_term) {
    $build = [];
    $allRecords = $this->getTaxonomyTermRecords($taxonomy_term);

    // Load the entities so we can render them.
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

    // If the view has been deleted, we're done.
    $view = Views::getView($this->getViewName());
    if (!$view) {
      return $build;
    }

    // Render the referenced entities.
    // @see mukurtu_taxonomy_views_pre_view.
    $referencedContent = [
      '#type' => 'view',
      '#name' => $this->getViewName(),
      '#display_id' => 'content_block',
      '#embed' => TRUE,
    ];

    // Facets.
    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = \Drupal::entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => $this->getFacetSourceId()]);

    // Render the facet block for each of them.
    $facets = [];
    if ($facetEntities) {
      $block_manager = \Drupal::service('plugin.manager.block');
      foreach ($facetEntities as $facet_id => $facetEntity) {
        $config = [];
        $block_plugin = $block_manager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
        if ($block_plugin && $block_plugin->access(\Drupal::currentUser())) {
            $facets[$facet_id] = $block_plugin->build();
        }
      }
    }

    $build['records'] = [
      '#theme' => 'taxonomy_records',
      '#active' => 1,
      '#records' => $records,
      '#referenced_content' => $referencedContent,
      '#facets' => $facets,
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
    // In the future when we support taxonomy record relationships for other
    // content types, we may need to fetch their enabled vocabs and append them
    // here.
    $enabledVocabs = $config->get('person_records_enabled_vocabularies') ?? [];

    // If the term vocabulary is not enabled for taxonomy records, return
    // an empty array.
    if (!in_array($taxonomy_term->bundle(), $enabledVocabs)) {
      return [];
    }

    $query = $this->entityTypeManager->getStorage('node')->getQuery();
    $query->condition('field_other_names', $taxonomy_term->id());
    $query->condition('status', 1, '=');
    $query->accessCheck(TRUE);
    $results = $query->execute();
    return empty($results) ? [] : $this->entityTypeManager->getStorage('node')->loadMultiple($results);
  }

}
