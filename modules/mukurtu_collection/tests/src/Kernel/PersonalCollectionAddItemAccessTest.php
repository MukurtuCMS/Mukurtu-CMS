<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_collection\Controller\PersonalCollectionAddItemController;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests which content gets the "Add to Personal Collection" tab.
 *
 * A personal collection only holds the bundles its items field accepts, so
 * the tab (and its dialog route) is denied for anything else, such as
 * articles and basic pages.
 */
#[Group('mukurtu_collection')]
class PersonalCollectionAddItemAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The same set as PersonalCollectionItemsBrowseTest, which explains why
   * each is needed to install the node and personal_collection schemas.
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
   * A user who may add personal collections.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $account;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('personal_collection');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('path_alias');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    // The "everyone may view published content" grant a real install writes.
    $this->container->get('node.grant_storage')->writeDefault();
    $this->installConfig(['system']);

    foreach (['article' => 'Article', 'page' => 'Basic page', 'person' => 'Person'] as $type => $name) {
      NodeType::create(['type' => $type, 'name' => $name])->save();
    }

    Role::create(['id' => 'authenticated', 'label' => 'authenticated'])
      ->grantPermission('access content')
      ->grantPermission('add personal collection entities')
      ->save();

    // User 1 bypasses access checks, so use a second account.
    User::create(['name' => 'admin'])->save();
    $this->account = User::create(['name' => 'collector', 'status' => 1]);
    $this->account->save();
  }

  /**
   * Checks the tab's access for a published node of the given bundle.
   */
  protected function accessFor(string $bundle): bool {
    // Content with no cultural protocol is visible only to its owner.
    $node = Node::create([
      'type' => $bundle,
      'title' => $bundle,
      'status' => 1,
      'uid' => $this->account->id(),
    ]);
    $node->save();
    return (new PersonalCollectionAddItemController())->access($this->account, $node)->isAllowed();
  }

  /**
   * Bundles the items field accepts get the tab; others don't.
   */
  public function testOnlyAcceptedBundlesGetTheTab(): void {
    $this->assertTrue($this->accessFor('person'));
    $this->assertFalse($this->accessFor('article'));
    $this->assertFalse($this->accessFor('page'));
  }

  /**
   * The bundle check reads the items field's target bundles.
   */
  public function testBundleCheckMatchesItemsField(): void {
    $target_bundles = \Drupal::service('entity_field.manager')
      ->getBaseFieldDefinitions('personal_collection')['field_items_in_collection']
      ->getSetting('handler_settings')['target_bundles'];
    $this->assertArrayNotHasKey('article', $target_bundles);

    foreach (['person' => TRUE, 'article' => FALSE] as $bundle => $expected) {
      $node = Node::create(['type' => $bundle, 'title' => $bundle]);
      $this->assertSame($expected, PersonalCollectionAddItemController::isValidPersonalCollectionItemBundle($node));
    }
  }

}
