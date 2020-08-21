<?php

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\NodeInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;
use Drupal\Tests\node\Traits\NodeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\RoleInterface;
use Drupal\Core\Language\Language;

/**
 * Tests the MukurtuProtocolManager.
 *
 * @group mukurtu_protocol
 */
class MukurtuProtocolManagerTest extends KernelTestBase {

  use NodeCreationTrait {
    getNodeByTitle as drupalGetNodeByTitle;
    createNode as drupalCreateNode;
  }
  use UserCreationTrait {
    createUser as drupalCreateUser;
    createRole as drupalCreateRole;
    createAdminRole as drupalCreateAdminRole;
  }
  use ContentTypeCreationTrait {
    createContentType as drupalCreateContentType;
  }

  protected $protocol_manager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'datetime',
    'user',
    'system',
    'filter',
    'field',
    'text',
    'media',
    'image',
    'og',
    'options',
    'entity_test',
    'mukurtu_protocol',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installSchema('system', 'sequences');
    $this->installSchema('node', 'node_access');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('media');
    $this->installEntitySchema('og_membership');
    $this->installConfig('filter');
    $this->installConfig('node');
    $this->installConfig('og');
    $this->installConfig('mukurtu_protocol');

    $this->protocol_manager = \Drupal::service('mukurtu_protocol.protocol_manager');

    // Clear permissions for authenticated users.
    $this->config('user.role.' . RoleInterface::AUTHENTICATED_ID)
      ->set('permissions', [])
      ->save();

    // Create user 1 who has special permissions.
    $this->drupalCreateUser();

    // Create a node type.
    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
      'display_submitted' => FALSE,
    ]);

  }

  /**
   * Test Protocol Access.
   */
  public function testProtocolAccess() {
    $user1 = $this->drupalCreateUser([
      'access content',
      'edit own page content',
      'delete own page content',
    ]);

    $user2 = $this->drupalCreateUser([
      'access content',
      'edit own page content',
      'delete own page content',
    ]);

    // Test user making a private item.
    $user1PrivateNode = $this->drupalCreateNode([
      'type' => 'page',
      'uid' => $user1->id(),
      MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE => MUKURTU_PROTOCOL_PERSONAL,
      MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE => MUKURTU_PROTOCOL_PERSONAL,
    ]);

    // The author should be able to do all operations.
    $this->assertProtocolAccess([
      'view' => TRUE,
      'update' => TRUE,
      'delete' => TRUE,
    ], $user1PrivateNode, $user1);

    // Non-author should not be able to do anything.
    $this->assertProtocolAccess([
      'view' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
    ], $user1PrivateNode, $user2);

    // Public item.
    $user1PublicNode = $this->drupalCreateNode([
      'type' => 'page',
      'uid' => $user1->id(),
      MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE => MUKURTU_PROTOCOL_PUBLIC,
      MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE => MUKURTU_PROTOCOL_PERSONAL,
    ]);

    // The author should be able to do all operations.
    $this->assertProtocolAccess([
      'view' => TRUE,
      'update' => TRUE,
      'delete' => TRUE,
    ], $user1PublicNode, $user1);

    // Non-author should be able to read but not write.
    $this->assertProtocolAccess([
      'view' => TRUE,
      'update' => FALSE,
      'delete' => FALSE,
    ], $user1PublicNode, $user2);

/*
    // Protocols for testing.
    $user1group1 = $this->drupalCreateNode([
      'type' => 'protocol',
      'uid' => $user1->id(),
    ]);

    $user1group2 = $this->drupalCreateNode([
      'type' => 'protocol',
      'uid' => $user1->id(),
    ]);

    $user2group1 = $this->drupalCreateNode([
      'type' => 'protocol',
      'uid' => $user2->id(),
    ]);

    $user2group2 = $this->drupalCreateNode([
      'type' => 'protocol',
      'uid' => $user2->id(),
    ]);

    // Testing "ANY". Node has two protocols, one that each user is a member of.
    $user1AnyNode = $this->drupalCreateNode([
      'type' => 'page',
      'uid' => $user1->id(),
      MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE => MUKURTU_PROTOCOL_ANY,
      //MUKURTU_PROTOCOL_FIELD_NAME_READ => [Language::LANGCODE_NOT_SPECIFIED => [['target_id' => $user1group1->id()]]],
      //MUKURTU_PROTOCOL_FIELD_NAME_READ => [$user1group1->id(), $user2group1->id()],
      MUKURTU_PROTOCOL_FIELD_NAME_WRITE_SCOPE => MUKURTU_PROTOCOL_PERSONAL,
    ]);

    $user1AnyNode->set(MUKURTU_PROTOCOL_FIELD_NAME_READ, [$user1group1->id(), $user2group1->id()]);
    $user1AnyNode->save();


    // User 1 should have all access.
    $this->assertProtocolAccess([
      'view' => TRUE,
      'update' => TRUE,
      'delete' => TRUE,
    ], $user1AnyNode, $user1);

    // User 2 should have be able to read.
    $this->assertProtocolAccess([
      'view' => TRUE,
      'update' => FALSE,
      'delete' => FALSE,
    ], $user1AnyNode, $user2);


    // Testing "ALL". Node has two protocols, one that each user is a member of.
    $user1AllNode = $this->drupalCreateNode([
      'type' => 'page',
      'uid' => $user1->id(),
      MUKURTU_PROTOCOL_FIELD_NAME_READ_SCOPE => MUKURTU_PROTOCOL_ALL,
     // MUKURTU_PROTOCOL_FIELD_NAME_READ => [$user1group1->id(), $user2group1->id()],
    ]);
    $user1AllNode->set(MUKURTU_PROTOCOL_FIELD_NAME_READ, [$user1group1->id(), $user2group1->id()]);
    $user1AllNode->save();

    // Neither user should have access, they are only members of one protocol.
    $this->assertProtocolAccess([
      'view' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
    ], $user1AllNode, $user1);

    $this->assertProtocolAccess([
      'view' => FALSE,
      'update' => FALSE,
      'delete' => FALSE,
    ], $user1AllNode, $user2); */
  }

  /**
   * Asserts that protocol access correctly grants or denies access.
   *
   * @param array $ops
   *   An associative array of the expected protocol access grants for the
   *   entity/account, with each key as the name of an operation (e.g. 'view',
   *   'delete') and each value a Boolean indicating whether access to that
   *   operation should be granted.
   * @param Drupal\Core\Entity\EntityInterface $entity
   *   The entity object to check.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account for which to check access.
   */
  public function assertProtocolAccess(array $ops, EntityInterface $entity, AccountInterface $account)
  {
    foreach ($ops as $op => $result) {
      $this->assertEquals(
        $result,
        $this->protocol_manager->checkAccess($entity, $op, $account)->isAllowed(),
        $this->nodeAccessAssertMessage($op, $result, $entity->language()->getId())
      );
    }
  }

  /**
   * Constructs an assert message to display which protocol access was tested.
   *
   * @param string $operation
   *   The operation to check access for.
   * @param bool $result
   *   Whether access should be granted or not.
   * @param string|null $langcode
   *   (optional) The language code indicating which translation of the entity
   *   to check. If NULL, the untranslated (fallback) access is checked.
   *
   * @return string
   *   An assert message string which contains information in plain English
   *   about the protocol access permission test that was performed.
   */
  public function nodeAccessAssertMessage($operation, $result, $langcode = NULL)
  {
    return new FormattableMarkup(
      'Protocol access returns @result with operation %op, language code %langcode.',
      [
        '@result' => $result ? 'true' : 'false',
        '%op' => $operation,
        '%langcode' => !empty($langcode) ? $langcode : 'empty',
      ]
    );
  }
}
