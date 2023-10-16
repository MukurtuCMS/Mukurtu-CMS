<?php

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\node\Entity\Node;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Plugin\Field\FieldType\CulturalProtocolItem;

class CulturalProtocolItemTest extends ProtocolAwareEntityTestBase {

  protected $communities;
  protected $protocols;

  protected $entity;

  /**
   * The user account with high levels of access to all protocols.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $privilegedUser;

  protected function setUp() {
    parent::setUp();

    $user = $this->createUser();
    $user->save();
    $this->privilegedUser = $user;

    foreach (range(0,2) as $delta) {
      $community = Community::create([
        'name' => "Community $delta",
      ]);
      $community->save();
      $this->communities[$delta] = $community;

      $protocol = Protocol::create([
        'name' => "Protocol $delta - Open",
        'field_communities' => [$community->id()],
        'field_access_mode' => 'open',
      ]);
      $protocol->save();
      $this->protocols[$delta] = $protocol;

      $this->communities[$delta]->addMember($this->privilegedUser);
      $this->protocols[$delta]->addMember($this->privilegedUser, ['protocol_steward']);
    }

    $entity = Node::create([
      'title' => $this->randomString(),
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $this->entity = $entity;
  }

  /**
   * Test unformatProtocols method. Could be a unit test but we're sneaking it
   * in here with all the other CulturalProtocolItem tests.
   */
  public function testUnformatProtocols() {
    $this->assertEqual([17, 2133, 42], CulturalProtocolItem::unformatProtocols("|17|,|2133|,|42|"));
    $this->assertEqual([42], CulturalProtocolItem::unformatProtocols("|42|"));
    $this->assertEqual([], CulturalProtocolItem::unformatProtocols(""));
  }

  /**
   * Test formatProtocols method. Could be a unit test but we're sneaking it
   * in here with all the other CulturalProtocolItem tests.
   */
  public function testFormatProtocols() {
    $this->assertEqual("|17|,|42|,|2133|", CulturalProtocolItem::formatProtocols([17, 2133, 42]));
    $this->assertEqual("|42|", CulturalProtocolItem::formatProtocols([42]));
    $this->assertEqual("", CulturalProtocolItem::formatProtocols([]));
  }

  /**
   * New entity, all valid protocols.
   */
  public function testNewEntityAllValidProtocols() {
    $entity = $this->entity;

    foreach (range(0,2) as $delta) {
      $this->communities[$delta]->addMember($this->currentUser);
      $this->protocols[$delta]->addMember($this->currentUser, ['protocol_steward']);
    }

    $protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($protocol_ids);
    $entity->save();
    $this->assertEqual($protocol_ids, $entity->getProtocols());
  }

  /**
   * New entity, some valid protocols.
   */
  public function testNewEntitySomeValidProtocols() {
    $entity = $this->entity;

    foreach (range(0, 1) as $delta) {
      $this->communities[$delta]->addMember($this->currentUser);
      $this->protocols[$delta]->addMember($this->currentUser, ['protocol_steward']);
    }

    $protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $valid_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($protocol_ids);
    $entity->save();
    $this->assertEqual($valid_ids, $entity->getProtocols());
  }

  /**
   * New entity. User has no valid protocol memberships.
   */
  public function testNewEntityNoValidProtocols() {
    $entity = $this->entity;

    // Try to set protocols.
    $protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($protocol_ids);
    $entity->save();

    // Should result in no protocols on the items.
    $this->assertEqual([], $entity->getProtocols());
  }

  /**
   * Updated entity, all valid protocols.
   */
  public function testUpdatedEntityAllValidProtocols() {
    $entity = $this->entity;

    foreach (range(0, 2) as $delta) {
      $this->communities[$delta]->addMember($this->currentUser);
      $this->protocols[$delta]->addMember($this->currentUser, ['protocol_steward']);
    }

    // Set one valid protocol.
    $original_protocol_ids = [$this->protocols[0]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEqual($original_protocol_ids, $entity->getProtocols());

    // Set three valid protocols.
    $new_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEqual($new_protocol_ids, $entity->getProtocols());
  }

  /**
   * Updated entity, some valid protocols.
   */
  public function testUpdatedEntityRemovedInaccessibleProtocol() {
    $entity = $this->entity;

    foreach (range(0, 1) as $delta) {
      $this->communities[$delta]->addMember($this->currentUser);
      $this->protocols[$delta]->addMember($this->currentUser, ['protocol_steward']);
    }

    // privilegedUser sets all protocols.
    $this->setCurrentUser($this->privilegedUser);
    $entity->setOwner($this->privilegedUser);
    $original_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEqual($original_protocol_ids, $entity->getProtocols());

    // Other user tries to unset a protocol they can't apply.
    $this->setCurrentUser($this->currentUser);
    $new_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEqual($original_protocol_ids, $entity->getProtocols());
  }

  /**
   * Test on updated entity where user tries to both add and remove protocols
   * they cannot apply.
   */
  public function testUpdatedEntityRemovedAndAddedInaccessibleProtocols() {
    // Current user can apply protocol 0.
    $entity = $this->entity;
    $this->communities[0]->addMember($this->currentUser);
    $this->protocols[0]->addMember($this->currentUser, ['protocol_steward']);

    // privilegedUser sets protocols 0 and 1.
    $this->setCurrentUser($this->privilegedUser);
    $entity->setOwner($this->privilegedUser);
    $original_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEqual($original_protocol_ids, $entity->getProtocols());

    // Current user tries to unset and set protocols they can't apply.
    $this->setCurrentUser($this->currentUser);
    $new_protocol_ids = [$this->protocols[2]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEqual([$this->protocols[1]->id()], $entity->getProtocols());
  }

}
