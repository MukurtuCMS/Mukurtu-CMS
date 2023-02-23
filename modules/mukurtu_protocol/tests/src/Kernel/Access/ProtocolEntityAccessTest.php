<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\og\Entity\OgRole;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Tests access to protocols.
 *
 * @group mukurtu_protocol
 */
class ProtocolEntityAccessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'field',
    'node',
    'node_access_test',
    'media',
    'image',
    'file',
    'filter',
    'og',
    'options',
    'system',
    'text',
    'taxonomy',
    'user',
    'mukurtu_core',
    'mukurtu_protocol',
  ];

  /**
   * Test Communities.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface[]
   */
  protected $communities;

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

    ///
    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('media');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installConfig(['og','node','media', 'filter']);


    // Flag protocol and community entities as Og groups
    // so Og does its part for access control.
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

    // Create a user role for a standard authenticated user.
    $role = Role::create([
      'id' => 'authenticated',
      'label' => 'authenticated',
    ]);
    $role->grantPermission('access content');
    $role->save();

    // Create test communities.
    foreach ([0, 1, 2] as $delta) {
      $community = Community::create([
        'name' => "Community $delta",
      ]);
      $community->save();
      $this->communities[$delta] = $community;
    }

    // Create the community manager role with our
    // default Mukurtu permission set.
    $values = [
      'name' => 'community_manager',
      'label' => 'Community Manager',
      'permissions' => [
        'update group',
        'approve and deny subscription',
        'add user',
        'manage members',
        'create protocol protocol',
        'delete any protocol protocol',
        'delete own protocol protocol',
        'update any protocol protocol',
        'update own protocol protocol',
      ],
    ];
    $communityManagerRole = OgRole::create($values);
    $communityManagerRole->setGroupType('community');
    $communityManagerRole->setGroupBundle('community');
    $communityManagerRole->save();

    // User to own content in tests where the tested user shouldn't
    // be the owner.
    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;

  }

  /**
   * Run a set of protocol entity access scenarios.
   *
   * @param mixed $scenarios
   *   The array of scenarios.
   */
  protected function runScenarios($scenarios) {
    // Create the protocol to test.
    $protocol = Protocol::create([
      'title' => $this->randomString(),
      'type' => 'protocol',
      'status' => TRUE,
      'uid' => $this->owner->id(),
    ]);
    $protocol->setCommunities($this->communities);
    $protocol->save();

    foreach ($scenarios as $scenario) {
      // Create fresh user with no initial community memberships.
      $user = User::create([
        'name' => $this->randomString(),
      ]);
      $user->save();

      // Adjust content owner.
      $scenario['owner'] ? $protocol->setOwner($user) : $protocol->setOwner($this->owner);

      // Change owning communities.
      $protocol->setCommunities(array_values($scenario['communities']));

      // Change access mode.
      $protocol->setSharingSetting($scenario['access_mode']);
      $protocol->save();

      // Add user to the communities with the requested roles.
      foreach ($scenario['memberships'] as $communityId => $roles) {
        if (!empty($roles)) {
          $scenario['communities'][$communityId]->addMember($user, $roles);
        }
      }

      // Run the access checks.
      foreach ($scenario['expected_access'] as $operation => $result) {
        $message = $this->buildScenarioMessage($scenario, $operation);
        $this->assertEquals($result, $protocol->access($operation, $user), $message);
      }
    }
  }

  /**
   * Construct the detailed output message for a given scenario.
   */
  protected function buildScenarioMessage($scenario, $operation): string {
    $communityList = "Communities = [" . implode(',', array_keys($scenario['communities'])) . "]";
    $owner = "Owner = " . ($scenario['owner'] ? 'true' : 'false');
    $memberships = "Memberships = " . print_r($scenario['memberships'], TRUE);
    $op = "Operation: $operation";
    $expectation = "Expecting: " . ($scenario['expected_access'][$operation] ? 'true' : 'false');
    return "[$op, $expectation]: $communityList, $owner, $memberships";
  }

  /**
   * Test strict protocol access with a single owning community.
   */
  public function testStrictProtocolOneCommunity() {
    $scenarios = [
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ];

    $this->runScenarios($scenarios);
  }

  /**
   * Test open protocol access with a single owning community.
   */
  public function testOpenProtocolOneCommunity() {
    $scenarios = [
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ];

    $this->runScenarios($scenarios);
  }

  /**
   * Test strict protocol access with two owning communities.
   */
  public function testStrictProtocolTwoCommunities() {
    $scenarios = [
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => [],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => [],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => FALSE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['community_manager'],
        ],
        'owner' => FALSE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['community_manager'],
        ],
        'owner' => TRUE,
        'access_mode' => 'strict',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ];

    $this->runScenarios($scenarios);
  }

  /**
   * Test open protocol access with two owning communities.
   */
  public function testOpenProtocolTwoCommunities() {
    $scenarios = [
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => [],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => [],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => [],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => [],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['member'],
          'community2' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['member'],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['member'],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => FALSE,
          'delete' => FALSE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['community_manager'],
        ],
        'owner' => FALSE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
      [
        'communities' => [
          'community1' => $this->communities[0],
          'community2' => $this->communities[1],
        ],
        'memberships' => [
          'community1' => ['community_manager'],
          'community2' => ['community_manager'],
        ],
        'owner' => TRUE,
        'access_mode' => 'open',
        'expected_access' => [
          'view' => TRUE,
          'update' => TRUE,
          'delete' => TRUE,
        ],
      ],
    ];

    $this->runScenarios($scenarios);
  }

}
