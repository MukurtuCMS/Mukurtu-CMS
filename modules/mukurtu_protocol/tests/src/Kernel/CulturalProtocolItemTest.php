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
   * The admin account (UID 1).
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $admin;

  /**
   * The user account with high levels of access to all protocols.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $privilegedUser;

  /**
   * Another user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $otherUser;

  protected function setUp() {
    parent::setUp();

    // Created in parent.
    $this->admin = $this->currentUser;

    $user = $this->createUser();
    $user->save();
    $this->privilegedUser = $user;

    $user = $this->createUser();
    $user->save();
    $this->otherUser = $user;

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

    $this->setCurrentUser($this->otherUser);
  }

  /**
   * Test unformatProtocols method. Could be a unit test but we're sneaking it
   * in here with all the other CulturalProtocolItem tests.
   */
  public function testUnformatProtocols() {
    $this->assertEquals([17, 2133, 42], CulturalProtocolItem::unformatProtocols("|17|,|2133|,|42|"));
    $this->assertEquals([42], CulturalProtocolItem::unformatProtocols("|42|"));
    $this->assertEquals([], CulturalProtocolItem::unformatProtocols(""));
  }

  /**
   * Test formatProtocols method. Could be a unit test but we're sneaking it
   * in here with all the other CulturalProtocolItem tests.
   */
  public function testFormatProtocols() {
    $this->assertEquals("|17|,|42|,|2133|", CulturalProtocolItem::formatProtocols([17, 2133, 42]));
    $this->assertEquals("|42|", CulturalProtocolItem::formatProtocols([42]));
    $this->assertEquals("", CulturalProtocolItem::formatProtocols([]));
  }

  /**
   * New entity, all valid protocols.
   */
  public function testNewEntityAllValidProtocols() {
    $entity = $this->entity;

    foreach (range(0,2) as $delta) {
      $this->communities[$delta]->addMember($this->otherUser);
      $this->protocols[$delta]->addMember($this->otherUser, ['protocol_steward']);
    }

    $protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($protocol_ids);
    $entity->save();
    $this->assertEquals($protocol_ids, $entity->getProtocols());
  }

  /**
   * New entity, some valid protocols.
   */
  public function testNewEntitySomeValidProtocols() {
    $entity = $this->entity;

    foreach (range(0, 1) as $delta) {
      $this->communities[$delta]->addMember($this->otherUser);
      $this->protocols[$delta]->addMember($this->otherUser, ['protocol_steward']);
    }

    $protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $valid_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($protocol_ids);
    $entity->save();
    $this->assertEquals($valid_ids, $entity->getProtocols());
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
    $this->assertEquals([], $entity->getProtocols());
  }

  /**
   * Updated entity, test all the setValue formats.
   */
  public function testUpdatedEntityProtocolsViaSetValue() {
    $entity = $this->entity;

    foreach (range(0, 2) as $delta) {
      $this->communities[$delta]->addMember($this->otherUser);
      $this->protocols[$delta]->addMember($this->otherUser, ['protocol_steward']);
    }

    // Set both sharing setting and protocols.
    $original_protocol_ids = [$this->protocols[0]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEquals($original_protocol_ids, $entity->getProtocols());

    // Set three valid protocols.
    $new_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    // Reload the entity fresh and set the protocols property directly.
    $node = $this->entityTypeManager->getStorage('node')->load($entity->id());
    $node->set('field_cultural_protocols', ['protocols' => $new_protocol_ids]);
    $node->save();
    $this->assertEquals('any', $node->getSharingSetting());
    $this->assertEquals($new_protocol_ids, $node->getProtocols());

    // Test the string format.
    $node->set('field_cultural_protocols', "all({$this->protocols[1]->id()},{$this->protocols[2]->id()})");
    $this->assertEquals('all', $node->getSharingSetting());
    $this->assertEquals([$this->protocols[1]->id(), $this->protocols[2]->id()], $node->getProtocols());

    // Test the string format with mixed case/spacing.
    $node->set('field_cultural_protocols', "ANy   ({$this->protocols[2]->id()},   {$this->protocols[0]->id()})    ");
    $this->assertEquals('any', $node->getSharingSetting());
    // Protocols get sorted, order is not maintained.
    $this->assertEquals([$this->protocols[0]->id(), $this->protocols[2]->id()], $node->getProtocols());

    // Test setting sharing setting only.
    $node->set('field_cultural_protocols', ['sharing_setting' => 'all']);
    $this->assertEquals('all', $node->getSharingSetting());
    $this->assertEquals([$this->protocols[0]->id(), $this->protocols[2]->id()], $node->getProtocols());

    // Test setting both properties.
    $node->set('field_cultural_protocols', ['sharing_setting' => 'any', 'protocols' => $new_protocol_ids]);
    $this->assertEquals('any', $node->getSharingSetting());
    $this->assertEquals($new_protocol_ids, $node->getProtocols());
  }

  /**
   * Updated entity, all valid protocols.
   */
  public function testUpdatedEntityAllValidProtocols() {
    $entity = $this->entity;

    foreach (range(0, 2) as $delta) {
      $this->communities[$delta]->addMember($this->otherUser);
      $this->protocols[$delta]->addMember($this->otherUser, ['protocol_steward']);
    }

    // Set one valid protocol.
    $original_protocol_ids = [$this->protocols[0]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEquals($original_protocol_ids, $entity->getProtocols());

    // Set three valid protocols.
    $new_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEquals($new_protocol_ids, $entity->getProtocols());
  }

  /**
   * Updated entity, some valid protocols.
   */
  public function testUpdatedEntityRemovedInaccessibleProtocol() {
    $entity = $this->entity;

    foreach (range(0, 1) as $delta) {
      $this->communities[$delta]->addMember($this->otherUser);
      $this->protocols[$delta]->addMember($this->otherUser, ['protocol_steward']);
    }

    // privilegedUser sets all protocols.
    $this->setCurrentUser($this->privilegedUser);
    $entity->setOwner($this->privilegedUser);
    $original_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id(), $this->protocols[2]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEquals($original_protocol_ids, $entity->getProtocols());

    // Other user tries to unset a protocol they can't apply.
    $this->setCurrentUser($this->otherUser);
    $new_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEquals($original_protocol_ids, $entity->getProtocols());
  }

  /**
   * Test on updated entity where user tries to both add and remove protocols
   * they cannot apply.
   */
  public function testUpdatedEntityRemovedAndAddedInaccessibleProtocols() {
    // Current user can apply protocol 0.
    $entity = $this->entity;
    $this->communities[0]->addMember($this->otherUser);
    $this->protocols[0]->addMember($this->otherUser, ['protocol_steward']);

    // privilegedUser sets protocols 0 and 1.
    $this->setCurrentUser($this->privilegedUser);
    $entity->setOwner($this->privilegedUser);
    $original_protocol_ids = [$this->protocols[0]->id(), $this->protocols[1]->id()];
    $entity->setSharingSetting('any');
    $entity->setProtocols($original_protocol_ids);
    $entity->save();
    $this->assertEquals($original_protocol_ids, $entity->getProtocols());

    // Current user tries to unset and set protocols they can't apply.
    $this->setCurrentUser($this->otherUser);
    $new_protocol_ids = [$this->protocols[2]->id()];
    $entity->setProtocols($new_protocol_ids);
    $entity->save();
    $this->assertEquals([$this->protocols[1]->id()], $entity->getProtocols());
  }

}
