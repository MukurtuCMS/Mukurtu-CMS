<?php

declare(strict_types=1);

namespace Drupal\mukurtu_taxonomy\Controller;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\Config;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\node\NodeInterface;
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
   * Singular display labels for Mukurtu's built-in vocabulary machine names.
   *
   * Vocabulary::label() returns the admin-supplied name (typically plural,
   * e.g. "Keywords"). This map provides the singular form used in page titles
   * ("Keyword: Art"). Any new Mukurtu vocabulary should be added here.
   * Custom site vocabularies not in this list fall back to the admin label.
   */
  const VOCABULARY_LABEL_MAP = [
    'category' => 'Category',
    'community_type' => 'Community Type',
    'contributor' => 'Contributor',
    'creator' => 'Creator',
    'format' => 'Format',
    'interpersonal_relationship' => 'Interpersonal Relationship',
    'keywords' => 'Keyword',
    'language' => 'Language',
    'location' => 'Location',
    'media_tag' => 'Media Tag',
    'people' => 'Person',
    'place_type' => 'Place Type',
    'publisher' => 'Publisher',
    'subject' => 'Subject',
    'type' => 'Type',
    'word_type' => 'Word Type',
  ];

  /**
   * Return the singular display label for a vocabulary machine name.
   */
  protected function getSingularVocabularyLabel(string $vocab): string {
    if (isset(self::VOCABULARY_LABEL_MAP[$vocab])) {
      return self::VOCABULARY_LABEL_MAP[$vocab];
    }
    $vocabulary = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->load($vocab);
    return $vocabulary ? $vocabulary->label() : $vocab;
  }

  /**
   * Return the page title for a taxonomy term page.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return string
   *   The page title in the format "Vocabulary Label: Term Name".
   */
  public function title(TermInterface $taxonomy_term): string {
    return $this->getSingularVocabularyLabel($taxonomy_term->bundle()) . ': ' . $taxonomy_term->label();
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
   *
   * @todo Facets are currently non-functional on taxonomy term pages.
   *   Referenced content now comes from the core taxonomy_term SQL view
   *   (taxonomy_index), not from Search API. The facets module integrates
   *   with Search API, not core Views, so any facets configured for these
   *   source IDs will load but won't filter the displayed content. Facet
   *   support requires either re-adding a Search API-backed view or a custom
   *   facet source plugin for the taxonomy_term view.
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
   * If the term maps to exactly one accessible person or place record,
   * redirects to that record instead of rendering the taxonomy term page.
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   *
   * @return array|\Drupal\Core\Routing\TrustedRedirectResponse
   *   A redirect to the record, or the full taxonomy term render array.
   */
  public function build(TermInterface $taxonomy_term): array|TrustedRedirectResponse {
    $record = $this->getSingleRecord($taxonomy_term, 'person', 'field_other_names', 'person_records_enabled_vocabularies')
      ?? $this->getSingleRecord($taxonomy_term, 'place', 'field_other_place_names', 'place_records_enabled_vocabularies');
    if ($record) {
      $url = $record->toUrl()->toString();
      $this->getLogger('mukurtu_taxonomy')->notice(
        'Taxonomy term %label (tid %tid) redirected to %type record nid %nid.',
        [
          '%label' => $taxonomy_term->label(),
          '%tid' => $taxonomy_term->id(),
          '%type' => $record->bundle(),
          '%nid' => $record->id(),
        ]
      );
      $response = new TrustedRedirectResponse($url);
      $cache = new CacheableMetadata();
      $cache->addCacheContexts(['user.node_grants:view']);
      $cache->addCacheableDependency($taxonomy_term);
      $cache->addCacheableDependency($record);
      $response->addCacheableDependency($cache);
      return $response;
    }

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

    // Use the core taxonomy_term view which queries taxonomy_index directly.
    // The mukurtu_taxonomy_references view filters by UUID via Search API
    // fulltext, but no UUID fields are indexed -- so it always returns empty.
    // taxonomy_index is always populated by Drupal's taxonomy system.
    // Views::getView() returns a new ViewExecutable instance each call
    // (see ViewExecutableFactory::get()), so overrideOption() mutations below
    // are scoped to this request only and don't affect other callers.
    $view = Views::getView('taxonomy_term');
    if (!$view) {
      return $build;
    }
    $view->setDisplay('default');
    $view->setArguments([$taxonomy_term->id()]);
    // Remove the term entity header -- title and intro text are rendered
    // by the page title callback and the taxonomy-records template instead.
    $view->display_handler->overrideOption('header', []);
    // Render items using the grid_browse view mode (vertical card: image top,
    // title below) which suits the column-count grid layout.
    $view->display_handler->overrideOption('row', [
      'type' => 'entity:node',
      'options' => ['view_mode' => 'grid_browse'],
    ]);
    $referencedContent = $view->buildRenderable('default');

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
      '#term_description' => $this->getTermDescription($taxonomy_term),
      '#attached' => [
        'library' => [
          'field_group/element.horizontal_tabs',
          'mukurtu_community_records/community-records'
        ],
      ],
    ];

    // When this term's vocabulary is person- or place-records-enabled, this
    // page could become a redirect if a matching node is later created and
    // linked via field_other_names / field_other_place_names. Tag the render
    // so that creating or editing any person/place node invalidates this
    // cache and re-runs the redirect check.
    // Trade-off: node_list:person / node_list:place fire on every save of
    // that bundle, so bulk imports will invalidate all term pages for that
    // vocabulary at once. This is acceptable for correctness; a term-scoped
    // tag would require a custom cache tag strategy.
    $person_vocabularies = $this->mukurtuTaxonomySettings->get('person_records_enabled_vocabularies') ?? [];
    $place_vocabularies = $this->mukurtuTaxonomySettings->get('place_records_enabled_vocabularies') ?? [];
    $cache = CacheableMetadata::createFromRenderArray($build);
    if (in_array($taxonomy_term->bundle(), $person_vocabularies)) {
      $cache->addCacheTags(['node_list:person']);
    }
    if (in_array($taxonomy_term->bundle(), $place_vocabularies)) {
      $cache->addCacheTags(['node_list:place']);
    }
    $cache->applyTo($build);

    return $build;
  }

  /**
   * Return the term description as a filtered Markup object, or empty string.
   *
   * Runs the stored description through check_markup() so HTML tags from the
   * term's text format are preserved and safe to output in Twig without |raw.
   */
  protected function getTermDescription(TermInterface $taxonomy_term): string|\Drupal\Core\Render\Markup {
    $description_field = $taxonomy_term->get('description');
    if ($description_field->isEmpty()) {
      return '';
    }
    $text = $description_field->value ?? '';
    $format = $description_field->format ?? 'basic_html';
    return $text ? check_markup($text, $format) : '';
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
   * Returns the single accessible record of the given bundle for a term.
   *
   * Only redirects when exactly one published, accessible record of the given
   * bundle has this term in the given field and the term's vocabulary is
   * enabled per the given settings key. Multiple matches return NULL so the
   * taxonomy page is shown instead.
   *
   * Key edge cases worth covering in tests: vocabulary not enabled (NULL),
   * zero matches (NULL), two or more matches (NULL), draft node excluded by
   * status=1, inaccessible node excluded by accessCheck(TRUE).
   *
   * @param \Drupal\taxonomy\TermInterface $taxonomy_term
   *   The taxonomy term.
   * @param string $bundle
   *   The node bundle to search, e.g. 'person' or 'place'.
   * @param string $field
   *   The "other names" field on that bundle, e.g. 'field_other_names'.
   * @param string $vocabularySetting
   *   The mukurtu_taxonomy.settings key listing enabled vocabularies for
   *   this bundle.
   */
  protected function getSingleRecord(TermInterface $taxonomy_term, string $bundle, string $field, string $vocabularySetting): ?NodeInterface {
    $vocabularies = $this->mukurtuTaxonomySettings->get($vocabularySetting) ?? [];
    if (!in_array($taxonomy_term->bundle(), $vocabularies)) {
      return NULL;
    }

    $storage = $this->entityTypeManager()->getStorage('node');
    $results = $storage->getQuery()
      ->condition('type', $bundle)
      ->condition($field, $taxonomy_term->id())
      ->condition('status', 1)
      ->accessCheck(TRUE)
      ->execute();

    if (count($results) !== 1) {
      return NULL;
    }

    $node = $storage->load(reset($results));
    return ($node && $node->access('view')) ? $node : NULL;
  }

  /**
   * Return content with the taxonomy record relationship for this term.
   */
  protected function getTaxonomyTermRecords(TermInterface $taxonomy_term): array {
    // In the future when we support taxonomy record relationships for other
    // content types, we may need to fetch their enabled vocabs and append them
    // here.
    $person_vocabularies = $this->mukurtuTaxonomySettings->get('person_records_enabled_vocabularies') ?? [];
    $place_vocabularies = $this->mukurtuTaxonomySettings->get('place_records_enabled_vocabularies') ?? [];

    $storage = $this->entityTypeManager()->getStorage('node');
    $results = [];

    if (in_array($taxonomy_term->bundle(), $person_vocabularies)) {
      $results += $storage->getQuery()
        ->condition('type', 'person')
        ->condition('field_other_names', $taxonomy_term->id())
        ->condition('status', 1, '=')
        ->accessCheck()
        ->execute();
    }

    if (in_array($taxonomy_term->bundle(), $place_vocabularies)) {
      $results += $storage->getQuery()
        ->condition('type', 'place')
        ->condition('field_other_place_names', $taxonomy_term->id())
        ->condition('status', 1, '=')
        ->accessCheck()
        ->execute();
    }

    return empty($results) ? [] : $storage->loadMultiple($results);
  }

}
