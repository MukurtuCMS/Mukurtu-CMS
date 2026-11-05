<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_browse\EventSubscriber\TermFacetNameRedirectSubscriber;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests nested term facets on the database search (#2522).
 *
 * @see \Drupal\mukurtu_search\NestedTermFacets
 * @see \Drupal\mukurtu_search\Hook\NestedTermFacetHooks
 * @see \Drupal\mukurtu_browse\EventSubscriber\TermFacetNameRedirectSubscriber
 */
#[Group('mukurtu_browse')]
class NestedTermFacetsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'facets',
    'facets_exposed_filters',
    'field',
    'filter',
    'mukurtu_browse',
    'mukurtu_search',
    'node',
    'search_api',
    'search_api_db',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
  ];

  /**
   * The index the tests use, named like the shipped content index.
   */
  const INDEX = 'mukurtu_default_content_index';

  /**
   * Category terms: Arts > Weaving > Baskets, Music, and two Springfields.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('node', ['node_access']);
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['search_api', 'node', 'filter']);
    $this->setUpCurrentUser([], ['access content']);

    Vocabulary::create(['vid' => 'category', 'name' => 'Category'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital heritage'])->save();
    foreach (['category', 'keywords'] as $vid) {
      FieldStorageConfig::create([
        'field_name' => "field_$vid",
        'entity_type' => 'node',
        'type' => 'entity_reference',
        'cardinality' => -1,
        'settings' => ['target_type' => 'taxonomy_term'],
      ])->save();
      FieldConfig::create([
        'field_name' => "field_$vid",
        'entity_type' => 'node',
        'bundle' => 'digital_heritage',
        'settings' => ['handler_settings' => ['target_bundles' => [$vid => $vid]]],
      ])->save();
    }

    Server::create([
      'id' => 'test_server',
      'name' => 'Test server',
      'backend' => 'search_api_db',
      'backend_config' => ['database' => 'default:default', 'min_chars' => 3, 'matching' => 'partial'],
      'status' => TRUE,
    ])->save();

    $arts = $this->createTerm('Arts');
    $weaving = $this->createTerm('Weaving', $arts);
    $baskets = $this->createTerm('Baskets', $weaving);
    $music = $this->createTerm('Music');
    $springfield_a = $this->createTerm('Springfield');
    $springfield_b = $this->createTerm('Springfield');
    $this->terms = compact('arts', 'weaving', 'baskets', 'music', 'springfield_a', 'springfield_b');
  }

  /**
   * Creates a category term.
   */
  protected function createTerm(string $name, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => 'category', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    return $term;
  }

  /**
   * Creates a published item in a category, returning its ID.
   */
  protected function createItem(string $title, TermInterface $category): int {
    $node = Node::create([
      'type' => 'digital_heritage',
      'title' => $title,
      'status' => 1,
      'field_category' => [$category->id()],
    ]);
    $node->save();
    return (int) $node->id();
  }

  /**
   * Creates the index with the given fields, keyed by field ID.
   */
  protected function createIndex(array $fields): void {
    $field_settings = [];
    foreach ($fields as $field_id => [$property_path, $type]) {
      $field_settings[$field_id] = [
        'label' => $field_id,
        'datasource_id' => 'entity:node',
        'property_path' => $property_path,
        'type' => $type,
      ];
    }
    Index::create([
      'id' => self::INDEX,
      'name' => 'Test index',
      'status' => TRUE,
      'server' => 'test_server',
      'datasource_settings' => ['entity:node' => []],
      'tracker_settings' => ['default' => []],
      'field_settings' => $field_settings,
      'options' => ['index_directly' => FALSE],
    ])->save();
  }

  /**
   * Gets the stored hierarchy processor fields.
   */
  protected function hierarchyFields(): ?array {
    return Index::load(self::INDEX)->get('processor_settings')['hierarchy']['fields'] ?? NULL;
  }

  /**
   * Term ID fields index ancestors, name fields don't, and new ones join.
   */
  public function testIndexHierarchyFollowsTermIdFields(): void {
    $this->createIndex([
      'category_tid' => ['field_category', 'integer'],
      'name' => ['field_category:entity:name', 'string'],
    ]);
    $this->assertSame(['category_tid' => 'taxonomy_term-parent'], $this->hierarchyFields());

    // A term ID field a site adds later nests too.
    $index = Index::load(self::INDEX);
    $index->addField(\Drupal::service('search_api.fields_helper')->createField($index, 'keywords_tid', [
      'label' => 'Keywords',
      'datasource_id' => 'entity:node',
      'property_path' => 'field_keywords',
      'type' => 'integer',
    ]));
    $index->save();
    $this->assertSame([
      'category_tid' => 'taxonomy_term-parent',
      'keywords_tid' => 'taxonomy_term-parent',
    ], $this->hierarchyFields());
  }

  /**
   * Filtering on a parent finds content tagged with any term nested under it.
   *
   * Two terms with the same name stay separate.
   */
  public function testParentFindsNestedContent(): void {
    $this->createIndex(['category_tid' => ['field_category', 'integer']]);
    $ids = [];
    foreach (['arts', 'weaving', 'baskets', 'music', 'springfield_a', 'springfield_b'] as $key) {
      $ids[$key] = $this->createItem("$key item", $this->terms[$key]);
    }
    Index::load(self::INDEX)->indexItems();

    $this->assertSame([$ids['arts'], $ids['weaving'], $ids['baskets']], $this->search('arts'));
    $this->assertSame([$ids['weaving'], $ids['baskets']], $this->search('weaving'));
    $this->assertSame([$ids['springfield_a']], $this->search('springfield_a'));
  }

  /**
   * Gets the node IDs filed under a term, in order.
   */
  protected function search(string $term_key): array {
    $query = Index::load(self::INDEX)->query();
    $query->addCondition('category_tid', $this->terms[$term_key]->id());
    $ids = array_map(fn ($id) => (int) preg_replace('/^entity:node\/(\d+):.*$/', '$1', $id), array_keys($query->execute()->getResultItems()));
    sort($ids);
    return $ids;
  }

  /**
   * Builds a facet filter like the shipped Category one, on another field.
   */
  protected function shippedCategoryFilter(string $field): array {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_browse');
    $shipped = Yaml::decode(file_get_contents("$path/config/install/views.view.mukurtu_digital_heritage_browse.yml"));
    $filter = $shipped['display']['default']['display_options']['filters']['facets_name'];
    $filter['field'] = $field;
    $filter['hierarchy'] = FALSE;
    unset($filter['facet']['hierarchy'], $filter['facet']['expand_hierarchy']);
    $filter['facet']['processor_configs'] = array_diff_key($filter['facet']['processor_configs'], [
      'translate_entity' => TRUE,
      'hierarchy_processor' => TRUE,
    ]);
    return $filter;
  }

  /**
   * Creates the digital heritage browse view with the given facet filters.
   */
  protected function createView(array $filters): void {
    View::create([
      'id' => 'mukurtu_digital_heritage_browse',
      'label' => 'Browse',
      'base_table' => 'search_api_index_' . self::INDEX,
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => ['filters' => $filters],
        ],
      ],
    ])->save();
  }

  /**
   * Gets a stored facet filter.
   */
  protected function storedFilter(string $filter_id): array {
    return View::load('mukurtu_digital_heritage_browse')->get('display')['default']['display_options']['filters'][$filter_id];
  }

  /**
   * Facets on term ID fields nest with translated labels; name facets don't.
   */
  public function testViewFacetsNest(): void {
    $this->createIndex([
      'category_tid' => ['field_category', 'integer'],
      'name' => ['field_category:entity:name', 'string'],
    ]);
    $this->createView([
      'facets_name' => $this->shippedCategoryFilter('facets_category_tid'),
      'facets_names' => ['id' => 'facets_names'] + $this->shippedCategoryFilter('facets_name'),
    ]);

    $nested = $this->storedFilter('facets_name');
    $this->assertTrue($nested['hierarchy']);
    $this->assertSame('taxonomy', $nested['facet']['hierarchy']);
    $this->assertFalse($nested['facet']['expand_hierarchy']);
    $this->assertArrayHasKey('translate_entity', $nested['facet']['processor_configs']);
    $this->assertArrayHasKey('hierarchy_processor', $nested['facet']['processor_configs']);

    $flat = $this->storedFilter('facets_names');
    $this->assertFalse($flat['hierarchy']);
    $this->assertArrayNotHasKey('translate_entity', $flat['facet']['processor_configs']);
  }

  /**
   * The update moves shipped facets onto term IDs and reindexes.
   *
   * A facet a site changed is left alone.
   */
  public function testUpdateShippedFacets(): void {
    $this->createIndex(['name' => ['field_category:entity:name', 'string']]);
    $this->createView([
      'facets_name' => $this->shippedCategoryFilter('facets_name'),
      'facets_name_1' => ['id' => 'facets_name_1'] + $this->shippedCategoryFilter('facets_custom'),
    ]);
    $this->createItem('Baskets item', $this->terms['baskets']);

    $sandbox = [];
    $service = \Drupal::service('mukurtu_search.nested_term_facets');
    $config_dir = \Drupal::service('extension.list.module')->getPath('mukurtu_browse') . '/config/install';
    $filters = [
      'mukurtu_digital_heritage_browse' => [
        'facets_name' => ['facets_name', 'facets_category_tid'],
        'facets_name_1' => ['facets_name_1', 'facets_keywords_tid'],
      ],
    ];
    do {
      $indexed = $service->updateShippedFacets($sandbox, $config_dir, self::INDEX, ['category_tid'], $filters);
    } while ($sandbox['#finished'] < 1);

    $this->assertSame(1, $indexed);
    $this->assertSame(['category_tid' => 'taxonomy_term-parent'], $this->hierarchyFields());
    $this->assertSame('facets_category_tid', $this->storedFilter('facets_name')['field']);
    $this->assertArrayHasKey('hierarchy_processor', $this->storedFilter('facets_name')['facet']['processor_configs']);
    $this->assertSame('facets_custom', $this->storedFilter('facets_name_1')['field']);
    $this->assertCount(1, $this->search('arts'));
  }

  /**
   * Old links that filter by term name redirect to the term's ID.
   *
   * A name shared by two terms is left alone.
   */
  public function testOldNameLinksRedirect(): void {
    $this->container->get('router.builder')->rebuild();
    $this->createIndex(['category_tid' => ['field_category', 'integer']]);
    $this->createView(['facets_name' => $this->shippedCategoryFilter('facets_category_tid')]);
    $subscriber = $this->container->get('mukurtu_browse.term_facet_name_redirect');

    $request = Request::create('/digital-heritage', 'GET', ['category' => ['Weaving' => 'Weaving', 'page' => 2]]);
    $request->attributes->set('_route', 'mukurtu_browse.browse_digital_heritage_page');
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->redirectTermNames($event);
    $this->assertSame(301, $event->getResponse()?->getStatusCode());
    parse_str((string) parse_url($event->getResponse()->getTargetUrl(), PHP_URL_QUERY), $query);
    $weaving = (string) $this->terms['weaving']->id();
    $this->assertSame([$weaving => $weaving, 'page' => '2'], $query['category']);

    $request = Request::create('/digital-heritage', 'GET', ['category' => ['Springfield' => 'Springfield']]);
    $request->attributes->set('_route', 'mukurtu_browse.browse_digital_heritage_page');
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $subscriber->redirectTermNames($event);
    $this->assertNull($event->getResponse());
  }

  /**
   * An old-link redirect is only ever served for the URL it came from.
   *
   * The Dynamic Page Cache keys responses by route and the response's cache
   * contexts. A redirect set after its request subscriber, without the query
   * string as a context, was cached for every query on the page, so one old
   * bookmark sent every later filtered visit to the same term, and its own
   * target into a loop. The cache doesn't run under PHPUnit's command line,
   * so this checks the two things that prevent it; browse.spec.ts checks the
   * behaviour on a real site.
   */
  public function testRedirectIsNotCachedForOtherQueries(): void {
    $priority = TermFacetNameRedirectSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][1];
    // After routing (32), before the Dynamic Page Cache (27).
    $this->assertGreaterThan(27, $priority);
    $this->assertLessThan(32, $priority);

    $this->container->get('router.builder')->rebuild();
    $this->createIndex(['category_tid' => ['field_category', 'integer']]);
    $this->createView(['facets_name' => $this->shippedCategoryFilter('facets_category_tid')]);
    $request = Request::create('/digital-heritage', 'GET', ['category' => ['Weaving' => 'Weaving']]);
    $request->attributes->set('_route', 'mukurtu_browse.browse_digital_heritage_page');
    $event = new RequestEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST);
    $this->container->get('mukurtu_browse.term_facet_name_redirect')->redirectTermNames($event);

    $this->assertContains('url.query_args', $event->getResponse()->getCacheableMetadata()->getCacheContexts());
  }

}
