<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests who can create a multipage item from an item.
 *
 * Follows the roles and permissions validation workbook: the account must be
 * able to see the item, and be a protocol steward of any one of its
 * protocols, whatever the sharing setting.
 */
#[Group('mukurtu_multipage_items')]
class MultipageItemCreateAccessTest extends MultipageItemTestBase {

  /**
   * Protocols by name: strict A, B and C, and open O.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol[]
   */
  protected array $p = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital Heritage'])->save();

    // The validator looks for community records by this field.
    FieldStorageConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'bundle' => 'digital_heritage',
      'label' => 'Original Record',
    ])->save();

    foreach (['A' => 'strict', 'B' => 'strict', 'C' => 'strict', 'O' => 'open'] as $name => $mode) {
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
      'administer multipage item',
      'create digital_heritage content',
    ]);
  }

  /**
   * Creates a saved item with the given protocols and sharing setting.
   */
  protected function item(array $protocols, string $sharing): NodeInterface {
    $node = Node::create([
      'type' => 'digital_heritage',
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
   * Whether the user can open the "new multipage item" page for the item.
   */
  protected function canCreate(NodeInterface $item, User $user): bool {
    // The validator checks the current user.
    $this->container->get('current_user')->setAccount($user);
    $result = MultipageItemPageController::create($this->container)->newFromNodeAccess($user, $item)->isAllowed();
    $this->container->get('current_user')->setAccount($this->currentUser);
    return $result;
  }

  /**
   * Tests an item in one protocol.
   */
  public function testSingleProtocol(): void {
    $this->assertTrue($this->canCreate($this->item(['A'], 'any'), $this->user(['A' => ['protocol_steward']])));
    $this->assertFalse($this->canCreate($this->item(['A'], 'any'), $this->user(['A' => []])));

    // Can see an open item, but isn't a steward of its protocol.
    $this->assertFalse($this->canCreate($this->item(['O'], 'any'), $this->user(['C' => ['protocol_steward']])));
  }

  /**
   * Tests an item in two protocols where the user stewards one of them.
   */
  public function testStewardOfOneProtocol(): void {
    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'any'), $this->user(['B' => ['protocol_steward']])));
    $this->assertTrue($this->canCreate($this->item(['A', 'B'], 'all'), $this->user(['A' => ['protocol_steward'], 'B' => []])));
    $this->assertTrue($this->canCreate($this->item(['O', 'B'], 'all'), $this->user(['B' => ['protocol_steward']])));

    // Not a member of A, so can't see the item.
    $this->assertFalse($this->canCreate($this->item(['A', 'B'], 'all'), $this->user(['B' => ['protocol_steward']])));
  }

}
