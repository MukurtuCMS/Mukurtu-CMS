<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\og\Traits\OgMembershipCreationTrait;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Tests access to content by protocol control.
 *
 * @group mukurtu_protocol
 */
class AccessByProtocolTest extends KernelTestBase {

  use ContentTypeCreationTrait;
  use OgMembershipCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'workflows',
    'field',
    'node',
    'node_access_test',
    'media',
    'og',
    'options',
    'system',
    'text',
    'user',
    'taxonomy',
    'mukurtu_protocol',
  ];

  /**
   * A test group.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $group;


  /**
   * A dummy community.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community;

  /**
   * The open test protocols.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   */
  protected $openProtocols;

  /**
   * The strict test protocols.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterface[]
   */
  protected $strictProtocols;

  /**
   * Test group content entities.
   *
   * @var \Drupal\Core\Entity\EntityInterface[]
   */
  protected $groupContent;

  /**
   * Test users.
   *
   * @var \Drupal\Core\Session\AccountInterface[]
   */
  protected $users;

  /**
   * A user not involved in testing to use as the owner for content.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['og']);
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('node');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', 'sequences');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');

    node_access_rebuild();

    // Flag protocol entity as an Og group so Og does its
    // part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a node type to test under protocol control.
    NodeType::create([
      'type' => 'thing',
      'name' => 'Protocol Controlled Thing',
    ])->save();

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->grantPermission('update own thing node');
    $role->grantPermission('delete own thing node');
    $role->save();

    // User to own content in tests where the tested user shouldn't
    // be the owner.
    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;

    $container = \Drupal::getContainer();
    $this->container
      ->get('current_user')
      ->setAccount($owner);

    // Create a community. Protocols require a community reference.
    $community = Community::create([
      'name' => 'Community 1',
    ]);
    $community->save();
    $this->community = $community;
    $community->addMember($owner);

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
        'create thing node',
        'delete any thing node',
        'delete own thing node',
        'update any thing node',
        'update own thing node',
        'manage members',
        'update group',
      ],
    ];
    $protocolStewardRole = OgRole::create($values);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    // Create the contributor role with our
    // default Mukurtu permission set for the thing node type.
    $values = [
      'name' => 'contributor',
      'label' => 'Contributor',
      'permissions' => [
        'create thing node',
        'delete own thing node',
        'update own thing node',
      ],
    ];
    $contributorRole = OgRole::create($values);
    $contributorRole->setGroupType('protocol');
    $contributorRole->setGroupBundle('protocol');
    $contributorRole->save();

    // Create three strict and three open protocols.
    foreach ([1, 2, 3] as $n) {
      $p = Protocol::create([
        'name' => "Strict $n",
        'field_communities' => [$this->community->id()],
        'field_access_mode' => 'strict',
      ]);
      $p->save();
      $this->strictProtocols[$n] = $p;
      $p->addMember($owner, ['protocol_steward']);

      $p = Protocol::create([
        'name' => "Open $n",
        'field_communities' => [$this->community->id()],
        'field_access_mode' => 'open',
      ]);
      $p->save();
      $this->openProtocols[$n] = $p;
      $p->addMember($owner, ['protocol_steward']);
    }

  }

  /**
   * Run a set of protocol access scenarios.
   *
   * @param string $access_setting
   *   The protocol control access setting (any/all).
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface[] $protocols
   *   The array of protocol entities to use in protocol control.
   * @param mixed $scenarios
   *   The array of scenarios.
   */
  protected function runProtocolControlScenarios($access_setting, array $protocols, $scenarios) {
    // Create the content to test.
    $content = Node::create([
      'title' => $this->randomString(),
      'type' => 'thing',
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);

//    assert($content instanceof CulturalProtocolControlledInterface);
    $this->assertInstanceOf(CulturalProtocolControlledInterface::class, $content);

    $content->setSharingSetting($access_setting);
    $content->setProtocols(array_values($protocols));
    $content->save();

    foreach ($scenarios as $scenario) {
      // Create fresh user with no initial protocol memberships.
      $user = User::create([
        'name' => $this->randomString(),
      ]);
      $user->save();

      // Adjust content owner.
      $scenario['owner'] ? $content->setOwner($user) : $content->setOwner($this->owner);

      // Add user to the protocols with the requested roles.
      foreach ($scenario['memberships'] as $protocolId => $roles) {
        if (!empty($roles)) {
          $protocols[$protocolId]->addMember($user, $roles);
        }
      }

      // Run the access checks.
      foreach ($scenario['expected_access'] as $operation => $result) {
        $message = $this->buildScenarioMessage($access_setting, $protocols, $scenario, $operation);
        $this->assertEquals($result, $content->access($operation, $user), $message);
      }
    }
  }

  /**
   * Construct the detailed output message for a given scenario.
   */
  protected function buildScenarioMessage($access_setting, array $protocols, $scenario, $operation): string {
    $access = "Access = $access_setting";
    $protocolList = "Protocols = [" . implode(',', array_keys($protocols)) . "]";
    $owner = "Owner = " . ($scenario['owner'] ? 'true' : 'false');
    $memberships = "Memberships = " . print_r($scenario['memberships'], TRUE);
    $op = "Operation: $operation";
    $expectation = "Expecting: " . ($scenario['expected_access'][$operation] ? 'true' : 'false');
    return "[$op, $expectation]: $access, $protocolList, $owner, $memberships";
  }
}
