<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\taxonomy\TermInterface;
use Drupal\views\Views;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for taxonomy record view.
 */
class TaxonomyRecordViewController extends ControllerBase implements ContainerInjectionInterface {

  /**
   * The search backend.
   *
   * @var string
   */
  protected string $backend;

  /**
   * The mukurtu taxonomy settings.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected Config $mukurtuTaxonomySettings;

  /**
   * Constructs a new TaxonomyRecordViewController object.
   *
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
   *   The entity field manager service.
   * @param \Drupal\Core\Block\BlockManagerInterface $blockManager
   *   The block manager service.
   */
  public function __construct(protected EntityFieldManagerInterface $entityFieldManager, protected BlockManagerInterface $blockManager) {
    $this->backend = $this->config('mukurtu_search.settings')->get('backend') ?? 'db';
    $this->mukurtuTaxonomySettings = $this->config('mukurtu_taxonomy.settings');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Return the machine name of the view to use based on the search backend config.
   *
   * @return string
   *   The machine name of the view.
   */
  protected function getViewName(): string {
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
  protected function getFacetSourceId(): string {
    $views = [
      'db' => 'search_api:views_block__mukurtu_taxonomy_references__content_block',
      'solr' => 'search_api:views_block__mukurtu_taxonomy_references_solr__content_block',
    ];

    return $views[$this->backend];
  }

  /**
   * Display the taxonomy term page.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return array
   *   The render array for the canonical taxonomy term page, complete with
   *   taxonomy term information, taxonomy term records, and facets.
   */
  public function build(TermInterface $taxonomy_term): array {
    $build = [];
    $allRecords = $this->getTaxonomyTermRecords($taxonomy_term);

    // Load the entities so we can render them.
    $entityViewBuilder = $this->entityTypeManager()->getViewBuilder('node');
    $langcode = $this->languageManager()->getCurrentLanguage()->getId();

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

    // Set the display and inject the taxonomy term UUID into the fulltext
    // search filter.
    $view->setDisplay('content_block');
    $filters = $view->display_handler->getOption('filters');
    $filters['search_api_fulltext']['value'] = $taxonomy_term->uuid();
    $view->display_handler->overrideOption('filters', $filters);

    // Build the renderable array from the view.
    $referencedContent = $view->buildRenderable('content_block');

    // Facets.
    // Load all facets configured to use our browse block as a datasource.
    $facetEntities = $this->entityTypeManager()
      ->getStorage('facets_facet')
      ->loadByProperties(['facet_source_id' => $this->getFacetSourceId()]);

    // Render the facet block for each of them.
    $facets = [];
    if ($facetEntities) {
      foreach ($facetEntities as $facet_id => $facetEntity) {
        $config = [];
        $block_plugin = $this->blockManager->createInstance('facet_block' . PluginBase::DERIVATIVE_SEPARATOR . $facet_id, $config);
        if ($block_plugin && $block_plugin->access($this->currentUser())) {
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
  protected function getCommunitiesLabel(EntityInterface $node): string {
    $communities = $node->get('field_communities')->referencedEntities();

    $communityLabels = [];
    foreach ($communities as $community) {
      // Skip any communities the user can't see.
      if (!$community->access('view', $this->currentUser())) {
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
  protected function getTaxonomyTermRecords(TermInterface $taxonomy_term): array {
    // In the future when we support taxonomy record relationships for other
    // content types, we may need to fetch their enabled vocabs and append them
    // here.
    $enabledVocabs = $this->mukurtuTaxonomySettings->get('person_records_enabled_vocabularies') ?? [];
    $enabledVocabs = $this->mukurtuTaxonomySettings->get('place_records_enabled_vocabularies') ?? [];
    // If the term vocabulary is not enabled for taxonomy records, return
    // an empty array.
    if (!in_array($taxonomy_term->bundle(), $enabledVocabs)) {
      return [];
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $query = $storage->getQuery();
    $query->condition('field_other_names', $taxonomy_term->id());
    $query->condition('status', 1, '=');
    $query->accessCheck();
    $results = $query->execute();
    return empty($results) ? [] : $storage->loadMultiple($results);
  }

}
