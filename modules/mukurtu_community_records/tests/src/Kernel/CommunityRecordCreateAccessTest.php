<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_community_records\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\mukurtu_community_records\Controller\CommunityRecordController;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgRole;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests who can create a community record from an item.
 *
 * Follows the "DH item in a single protocol" and "DH item in multiple
 * protocols" rows of the roles and permissions validation workbook: the
 * account must be able to see the item, and be a steward of any one of its
 * protocols, whatever the sharing setting.
 */
#[Group('mukurtu_community_records')]
class CommunityRecordCreateAccessTest extends CommunityRecordTestBase {

  /**
   * Protocols by name: strict A, B and C, and open O and P.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol[]
   */
  protected array $p = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The base class's 'page' bundle isn't under protocol control (see
    // mukurtu_protocol_entity_bundle_info_alter()), so use another one.
    NodeType::create(['type' => 'item', 'name' => 'Item'])->save();
    FieldConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'bundle' => 'item',
      'label' => 'Original Record',
    ])->save();
    $this->config('mukurtu_community_records.settings')
      ->set('allowed_community_record_bundles', ['item'])
      ->save();

    $role = OgRole::create([
      'name' => 'community_record_steward',
      'label' => 'Community Record Steward',
      'permissions' => ['administer community records'],
    ]);
    $role->setGroupType('protocol');
    $role->setGroupBundle('protocol');
    $role->save();

    foreach (['A' => 'strict', 'B' => 'strict', 'C' => 'strict', 'O' => 'open', 'P' => 'open'] as $name => $mode) {
      $this->p[$name] = Protocol::create([
        'name' => "Protocol $name",
        'field_communities' => [$this->community->id()],
        'field_access_mode' => $mode,
      ]);
      $this->p[$name]->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), [
      'administer community records',
    ]);
  }

  /**
   * Creates a saved item with the given protocols and sharing setting.
   */
  protected function item(array $protocols, string $sharing): NodeInterface {
    $node = Node::create([
      'type' => 'item',
      'title' => implode('+', $protocols) . " $sharing",
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting($sharing);
    $node->setProtocols(array_map(fn($name) => $this->p[$name], $protocols));
    $node->save();
    return $node;
  }

  /**
   * Creates a user with the given roles, keyed by protocol name.
   *
   * An empty role list makes a plain member.
   */
  protected function user(array $memberships): User {
    $user = User::create(['name' => $this->randomMachineName()]);
    $user->save();
    foreach ($memberships as $name => $roles) {
      $this->p[$name]->addMember($user, $roles);
    }
    return $user;
  }

  /**
   * Whether the user can open the "Create community record" page.
   */
  protected function canCreate(NodeInterface $item, User $user): bool {
    return (new CommunityRecordController())->access($item, $user)->isAllowed();
  }

  /**
   * Tests an item in one protocol.
   */
  public function testSingleProtocol(): void {
    $this->assertTrue($this->canCreate($this->item(['A'], 'any'), $this->user(['A' => ['protocol_steward']])));
    $this->assertTrue($this->canCreate($this->item(['A'], 'any'), $this->user(['A' => ['community_record_steward']])));
    $this->assertFalse($this->canCreate($this->item(['A'], 'any'), $this->user(['A' => []])));

    // Can see an open item, but isn't a steward of its protocol.
    $this->assertFalse($this->canCreate($this->item(['O'], 'any'), $this->user(['B' => ['protocol_steward']])));
    // Can't see a strict item.
    $this->assertFalse($this->canCreate($this->item(['A'], 'any'), $this->user(['B' => ['protocol_steward']])));
  }

  /**
   * Tests an item in two protocols where the user stewards one of them.
   */
  public function testStewardOfOneProtocol(): void {
    $stewardOfB = fn() => $this->user(['B' => ['protocol_steward']]);

    $this->assertTrue($this->canCreate($this->item(['O', 'B'], 'any'), $stewardOfB()));
    $this->assertTrue($this->canCreate($this->item(['O', 'B'], 'all'), $stewardOfB()));
    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'any'), $stewardOfB()));
    // Not a member of A, so can't see the item.
    $this->assertFalse($this->canCreate($this->item(['A', 'B'], 'all'), $stewardOfB()));

    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'any'), $this->user(['A' => [], 'B' => ['protocol_steward']])));
    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'all'), $this->user(['A' => ['protocol_steward'], 'B' => []])));
    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'all'), $this->user(['A' => ['community_record_steward'], 'B' => []])));
    $this->assertFalse($this->canCreate($this->item(['A', 'B'], 'all'), $this->user(['A' => [], 'B' => []])));
  }

  /**
   * Tests a steward of a protocol that isn't on the item.
   */
  public function testStewardOfOtherProtocol(): void {
    $stewardOfC = fn() => $this->user(['C' => ['protocol_steward']]);

    $this->assertFalse($this->canCreate($this->item(['O', 'P'], 'any'), $stewardOfC()));
    $this->assertFalse($this->canCreate($this->item(['O', 'P'], 'all'), $stewardOfC()));
    $this->assertFalse($this->canCreate($this->item(['O', 'B'], 'any'), $stewardOfC()));
  }

}
