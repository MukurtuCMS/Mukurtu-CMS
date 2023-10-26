<?php

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\node\Entity\NodeType;
use Drupal\Core\Session\AccountInterface;

class ProtocolAwareEntityTestBase extends EntityKernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'filter',
    'image',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'views',
    'mukurtu_core',
    'mukurtu_protocol',
  ];

  /**
   * The user account set as the current user in the tests.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  protected $container;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig(['filter', 'og', 'system']);
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    // Flag community entities as Og groups
    // so Og does its part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a type to put in the collection.
    NodeType::create([
      'type' => 'protocol_aware_content',
      'name' => 'Protocol Aware Content',
    ])->save();

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('create protocol_aware_content content');
    $role->save();

    // Create the protocol steward role with our
    // default Mukurtu permission set for the thing node type.
    $values = [
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'apply protocol',
        'administer permissions',
        'approve and deny subscription',
        'create protocol_aware_content node',
        'create collection content',
        'edit any collection content',
        'delete any collection content',
        'edit own collection content',
        'delete own collection content',
        'delete any protocol_aware_content node',
        'delete own protocol_aware_content node',
        'update any protocol_aware_content node',
        'update own protocol_aware_content node',
        'manage members',
        'update group',
      ],
    ];
    $protocolStewardRole = OgRole::create($values);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    $container = \Drupal::getContainer();
    $this->container = $container;

    // Create user and set as current user.
    $user = $this->createUser();
    $user->save();
    $this->currentUser = $user;

    $this->setCurrentUser($user);
  }

  protected function setCurrentUser(AccountInterface $account) {
    $this->container
      ->get('current_user')
      ->setAccount($account);
  }

}
