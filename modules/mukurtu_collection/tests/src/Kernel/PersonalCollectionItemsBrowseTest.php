<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use PHPUnit\Framework\Attributes\Group;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Config\FileStorage;
use Drupal\Core\Render\RenderContext;
use Drupal\mukurtu_collection\Entity\PersonalCollection;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Views;

/**
 * Tests the grid/list/map item browse view and formatter for personal collections.
 *
 * Regression coverage for #1993: personal collections used to render
 * field_items_in_collection with a plain entity_reference_entity_view
 * formatter instead of the mukurtu_collection_items_browse switcher that
 * regular collections already had.
 */
#[Group('mukurtu_collection')]
class PersonalCollectionItemsBrowseTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * This list matches Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase's
   * module set (plus leaflet_views/mukurtu_collection): installEntitySchema('node')
   * computes the shared node table schema across *every* registered bundle
   * field, including the Collection bundle's field_cultural_protocols,
   * field_collection_image, field_keywords, etc. -- so every field
   * type/target entity type those fields reference must be resolvable here
   * even though this test never creates a "collection" bundle node.
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'layout_builder',
    'leaflet',
    'leaflet_views',
    'media',
    'node',
    'og',
    'options',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_collection',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
  ];

  /**
   * The owner of the test personal collection and its items.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $owner;

  /**
   * The test personal collection.
   *
   * @var \Drupal\mukurtu_collection\Entity\PersonalCollectionInterface
   */
  protected $personalCollection;

  /**
   * The nodes referenced by the test personal collection, in curated order.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $items = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('personal_collection');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('path_alias');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system']);

    // Import only the specific shipped config this test exercises, rather
    // than installConfig()'ing the whole mukurtu_core/mukurtu_collection
    // module (which pulls in unrelated config -- media types, entity
    // browsers, taxonomy vocabularies, etc. -- with their own heavy module
    // dependency chains that have nothing to do with the grid/list/map
    // switcher being tested here).
    $this->importModuleConfig('mukurtu_collection', 'views.view.mukurtu_collection_items');
    $this->importModuleConfig('mukurtu_collection', 'core.entity_view_display.personal_collection.personal_collection.full');

    NodeType::create([
      'type' => 'page',
      'name' => 'Basic page',
    ])->save();

    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('view published personal collection entities');
    $role->save();

    $owner = User::create(['name' => $this->randomString()]);
    $owner->save();
    $this->owner = $owner;

    // Field rendering (FieldItemList::view()) checks entity/field access
    // against the current user, which otherwise defaults to anonymous
    // (uid 0, no permissions) in a Kernel test.
    \Drupal::service('current_user')->setAccount($owner);

    foreach (range(0, 2) as $delta) {
      $node = Node::create([
        // randomMachineName() (not randomString()) so the title is safe to
        // string-match against rendered, HTML-escaped output.
        'title' => $this->randomMachineName(),
        'type' => 'page',
        'status' => TRUE,
        'uid' => $owner->id(),
      ]);
      $node->save();
      $this->items[] = $node;
    }

    $this->personalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $owner->id(),
      'field_items_in_collection' => array_map(
        fn(Node $node) => $node->id(),
        $this->items
      ),
    ]);
    $this->personalCollection->save();
  }

  /**
   * Imports a single shipped config object from a module's config/install.
   */
  protected function importModuleConfig(string $module, string $name): void {
    $storage = new FileStorage(
      \Drupal::service('extension.list.module')->getPath($module) . '/config/install'
    );
    $data = $storage->read($name);
    $this->assertNotFalse($data, "Missing shipped config $name in module $module.");
    \Drupal::configFactory()->getEditable($name)->setData($data)->save(TRUE);
  }

  /**
   * Data provider of the personal-collection block display ids to test.
   */
  public static function personalCollectionDisplayProvider(): array {
    return [
      'list display' => ['mukurtu_personal_collection_items_block'],
      'grid display' => ['mukurtu_personal_collection_items_block_grid'],
    ];
  }

  /**
   * The new personal-collection displays return the collection's own items.
   */
  #[\PHPUnit\Framework\Attributes\DataProvider('personalCollectionDisplayProvider')]
  public function testPersonalCollectionDisplayReturnsItemsInOrder(string $displayId): void {
    $view = Views::getView('mukurtu_collection_items');
    $this->assertNotNull($view, 'The mukurtu_collection_items view exists.');

    $view->setDisplay($displayId);
    $view->preExecute([(string) $this->personalCollection->id()]);
    $view->execute();

    $resultNids = array_map(fn($row) => (int) $row->nid, $view->result);
    $expectedNids = array_map(fn(Node $node) => (int) $node->id(), $this->items);

    $this->assertSame($expectedNids, $resultNids, "The $displayId display must return the personal collection's items, in curated order.");
  }

  /**
   * The new displays are scoped to the given personal collection only.
   */
  public function testPersonalCollectionDisplayIsScopedToOwnItems(): void {
    $otherNode = Node::create([
      'title' => $this->randomString(),
      'type' => 'page',
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
    $otherNode->save();

    $otherPersonalCollection = PersonalCollection::create([
      'name' => $this->randomString(),
      'status' => TRUE,
      'uid' => $this->owner->id(),
      'field_items_in_collection' => [$otherNode->id()],
    ]);
    $otherPersonalCollection->save();

    $view = Views::getView('mukurtu_collection_items');
    $view->setDisplay('mukurtu_personal_collection_items_block');
    $view->preExecute([(string) $this->personalCollection->id()]);
    $view->execute();

    $resultNids = array_map(fn($row) => (int) $row->nid, $view->result);
    $this->assertNotContains((int) $otherNode->id(), $resultNids, 'A personal collection must not show another personal collection\'s items.');
  }

  /**
   * The field formatter picks the personal-collection displays by entity type.
   */
  public function testFormatterUsesPersonalCollectionDisplays(): void {
    $build = $this->personalCollection
      ->get('field_items_in_collection')
      ->view('full');

    // The field formatter output is the single mukurtu_collection_items_browse
    // theme element produced by CollectionItemsBrowseFormatter::viewElements().
    $element = $build[0] ?? NULL;
    $this->assertNotNull($element);
    $this->assertSame('mukurtu_collection_items_browse', $element['#theme'] ?? NULL);

    // Render only the list results (not the whole element, which would also
    // render the map results -- the Leaflet map style isn't fully
    // renderable in this minimal Kernel test environment, a pre-existing gap
    // in the identical, already-shipped map display, unrelated to this fix).
    $renderer = \Drupal::service('renderer');
    $listHtml = (string) $renderer->executeInRenderContext(new RenderContext(), function () use ($renderer, $element) {
      return $renderer->render($element['#list_results']);
    });

    // The grid/list/map switcher markup must be present (mukurtu-collection-items-browse.html.twig
    // renders the buttons statically alongside the results), and the
    // personal collection's own items must actually be listed -- regression
    // coverage for #1993, where personal collections fell back to a plain,
    // switcher-less item list.
    $this->assertArrayHasKey('#grid_results', $element);
    $this->assertArrayHasKey('#map_results', $element);
    foreach ($this->items as $node) {
      $this->assertStringContainsString($node->label(), $listHtml);
    }
  }

}
