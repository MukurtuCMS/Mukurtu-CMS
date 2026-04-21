<?php

namespace Drupal\Tests\search_api\Kernel\Processor;

use Drupal\Core\TypedData\DataDefinitionInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\search_api\Kernel\ResultsTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the "Role-based access" processor.
 *
 * @group search_api
 *
 * @see \Drupal\search_api\Plugin\search_api\processor\RoleAccess
 * @see search_api_test_entity_access()
 */
#[RunTestsInSeparateProcesses]
class RoleAccessTest extends ProcessorTestBase {

  use ResultsTrait;

  /**
   * The nodes created for testing.
   *
   * @var \Drupal\node\Entity\Node[]
   */
  protected $nodes;

  /**
   * An array of test users assigned to each role.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  protected $testUsers;

  /**
   * {@inheritdoc}
   */
  public function setUp($processor = NULL): void {
    parent::setUp('role_access');

    NodeType::create(['type' => 'page', 'name' => 'page'])->save();

    // Enable the programmable role-based access controls found in the
    // search_api_test module.
    // @see search_api_test_entity_access()
    \Drupal::state()->set('search_api_test_add_role_access_control', TRUE);

    // Create three test roles and the anonymous user role, all of which can
    // access content. Since the test adds its own test access hook for each
    // role, we do not want to test existing core access mechanisms.
    foreach (['foo', 'bar', 'baz', 'anonymous', 'authenticated'] as $role_id) {
      Role::create(['id' => $role_id, 'label' => $role_id])
        ->grantPermission('access content')
        ->save();
    }

    // Create a test user for each of the (non-anonymous) test roles.
    foreach (['foo', 'bar', 'baz'] as $role_id) {
      $test_user = User::create(['name' => $role_id]);
      $test_user->addRole($role_id);
      $test_user->save();
      $this->testUsers[$role_id] = $test_user;
    }

    // Insert the special-case anonymous user.
    $anonymous_user = User::create([
      'uid' => 0,
      'name' => '',
    ]);
    $anonymous_user->save();
    $this->testUsers['anonymous'] = $anonymous_user;

    // Insert the special case authenticated user.
    $authenticated_user = User::create([
      'name' => 'Authenticated',
    ]);
    $authenticated_user->save();
    $this->testUsers['authenticated'] = $authenticated_user;
  }

  /**
   * Tests the role based access filtering.
   */
  public function testRoleBasedAccess() {
    $allowed_foo = $this->createTestNode('allow for foo role');
    $allowed_bar = $this->createTestNode('allow for bar role');
    $allowed_anonymous = $this->createTestNode('allow for anonymous role');
    $allowed_authenticated = $this->createTestNode('allow for authenticated role');
    $allowed_all = $this->createTestNode('allow for foo, bar, baz, anonymous, authenticated role');

    $this->index->reindex();
    $this->indexItems();

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);

    $expected = [
      'foo'  => [
        'node' => [$allowed_all, $allowed_foo, $allowed_authenticated],
      ],
      'bar'  => [
        'node' => [$allowed_all, $allowed_bar, $allowed_authenticated],
      ],
      'baz'  => [
        'node' => [$allowed_all, $allowed_authenticated],
      ],
      'anonymous'  => [
        'node' => [$allowed_anonymous, $allowed_all],
      ],
      'authenticated'  => [
        'node' => [$allowed_authenticated, $allowed_all],
      ],
    ];
    foreach ($expected as $role_id => $expected_role_results) {
      $cloned_query = clone $query;
      $cloned_query->setOption('search_api_access_account', $this->testUsers[$role_id]);
      $result = $cloned_query->execute();
      $this->assertResults($result, $expected_role_results);
    }
  }

  /**
   * Tests whether the correct field values are created for nodes.
   */
  public function testComputedFieldValues() {
    $allowed_foo = $this->createTestNode('allow for foo role');
    $allowed_bar = $this->createTestNode('allow for bar role');
    $allowed_anonymous = $this->createTestNode('allow for anonymous role');
    $allowed_authenticated = $this->createTestNode('allow for authenticated role');
    $allowed_all = $this->createTestNode('allow for foo, bar, baz, anonymous, authenticated role');

    $expected_roles = [
      $allowed_foo => ['foo'],
      $allowed_bar => ['bar'],
      $allowed_anonymous => ['anonymous'],
      $allowed_authenticated => ['authenticated'],
      $allowed_all => ['foo', 'bar', 'baz', 'anonymous', 'authenticated'],
    ];
    $fields_helper = \Drupal::getContainer()->get('search_api.fields_helper');
    $datasource = $this->index->getDatasource('entity:node');
    foreach ($expected_roles as $i => $roles) {
      $node = $this->nodes[$i];
      $item = $fields_helper->createItemFromObject(
        $this->index,
        $node->getTypedData(),
        NULL,
        $datasource
      );
      $this->processor->addFieldValues($item);
      $this->assertEquals($roles, $item->getField('role_access')->getValues(), "Wrong roles computed for node \"{$node->label()}\".");
    }
  }

  /**
   * Tests whether the access check correctly defaults to the logged-in user.
   */
  public function testDefaultingToLoggedInUser() {
    $allowed_foo = $this->createTestNode('allow for foo role');

    \Drupal::currentUser()->setAccount($this->testUsers['foo']);

    $index_storage = \Drupal::entityTypeManager()->getStorage('search_api_index');
    $index_storage->resetCache([$this->index->id()]);
    $this->index = $index_storage->load($this->index->id());

    $this->index->reindex();
    $this->indexItems();

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);

    $result = $query->execute();
    $this->assertResults($result, [
      'node' => [$allowed_foo],
    ]);
  }

  /**
   * Tests the account in the processor when it's based on an ID.
   */
  public function testIdBasedCurrentUser() {
    $allowed_foo = $this->createTestNode('allow for foo role');
    $this->createTestNode('allow for bar role');

    $this->index->reindex();
    $this->indexItems();

    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index);
    $query->setOption('search_api_access_account', $this->testUsers['foo']->id());

    $result = $query->execute();
    $this->assertResults($result, [
      'node' => [$allowed_foo],
    ]);
  }

  /**
   * Tests whether the "search_api_bypass_access" query option is respected.
   */
  public function testQueryAccessBypass() {
    $disallowed = $this->createTestNode('disallowed');
    $this->index->reindex();
    $this->indexItems();
    $this->assertEquals(1, $this->index->getTrackerInstance()->getIndexedItemsCount());
    $query = \Drupal::getContainer()
      ->get('search_api.query_helper')
      ->createQuery($this->index, ['search_api_bypass_access' => TRUE]);
    $result = $query->execute();
    $expected = [
      'node' => [$disallowed],
    ];
    $this->assertResults($result, $expected);
  }

  /**
   * Tests whether the property is correctly added by the processor.
   */
  public function testAlterPropertyDefinitions() {
    // Check for added properties when no datasource is given.
    $properties = $this->processor->getPropertyDefinitions(NULL);
    $this->assertArrayHasKey('search_api_role_access', $properties);
    $property = $properties['search_api_role_access'];
    $this->assertInstanceOf(DataDefinitionInterface::class, $property);
    $this->assertEquals('string', $property->getDataType());

    // Verify that there are no properties if a datasource is given.
    $datasource = $this->index->getDatasource('entity:node');
    $properties = $this->processor->getPropertyDefinitions($datasource);
    $this->assertEquals([], $properties);
  }

  /**
   * Creates a test node and return its key in the test nodes array.
   *
   * @param string $title
   *   The title of the node.
   *
   * @return int
   *   The position of the node in the test array.
   */
  protected function createTestNode(string $title): int {
    $node = Node::create([
      'status' => NodeInterface::PUBLISHED,
      'type' => 'page',
      'title' => $title,
    ]);
    $node->save();
    $this->nodes[] = $node;
    end($this->nodes);
    return key($this->nodes);
  }

}
