<?php

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\User;
use Drupal\drafts_entity_test\Entity\TestDraftEntity;

/**
 * Test access to draft entities.
 *
 * @group mukurtu_drafts
 */
class MukurtuDraftsEntityTest extends KernelTestBase
{
  /**
   * {@inheritdoc}
   */
  public static $modules = ['system', 'entity_test', 'drafts_entity_test', 'user', 'mukurtu_drafts'];

  /**
   * A user who will serve as owner of the test entities.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp()
  {
    parent::setUp();
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('drafts_entity_test');
    $this->installEntitySchema('user');

    $owner = User::create([
      'name' => $this->randomString(),
    ]);
    $owner->save();
    $this->owner = $owner;
  }

  /**
   * Test access operations for a draft entity.
   */
  public function testAccessDraftEntity()
  {
    $entity = TestDraftEntity::create(['name' => 'draftEntity']);
    $entity->setDraft();
    $entity->setOwner($this->owner);

    // Non-owner.
    $user = User::create(['name' => $this->randomString()]);
    $user->save();

    // Note: we check the expected access result object instead of the usual
    // entity access because another access was overriding the expected result
    // of mukurtu_draft's hook_entity_access().

    $this->assertEquals(TRUE, $entity->access('view', $user, TRUE)->isForbidden());
    $this->assertEquals(TRUE, $entity->access('update', $user, TRUE)->isForbidden());
    $this->assertEquals(TRUE, $entity->access('delete', $user, TRUE)->isForbidden());

    // Owner.
    $this->assertEquals(TRUE, $entity->access('view', $this->owner, TRUE)->isAllowed());
    $this->assertEquals(TRUE, $entity->access('update', $this->owner, TRUE)->isAllowed());
    $this->assertEquals(TRUE, $entity->access('delete', $this->owner, TRUE)->isAllowed());
  }

  /**
   * Test access operations for a non-draft entity.
   */
  public function testAccessNonDraftEntity()
  {
    $entity = TestDraftEntity::create(['name' => 'nonDraftEntity']);
    $entity->unsetDraft();
    $entity->setOwner($this->owner);

    // Non-owner.
    $user = User::create([
      'name' => $this->randomString()
    ]);
    $user->save();

    $this->assertEquals(TRUE, $entity->access('view', $user, TRUE)->isNeutral());
    $this->assertEquals(TRUE, $entity->access('update', $user, TRUE)->isNeutral());
    $this->assertEquals(TRUE, $entity->access('delete', $user, TRUE)->isNeutral());

    // Owner.
    $this->assertEquals(TRUE, $entity->access('view', $this->owner, TRUE)->isAllowed());
    $this->assertEquals(TRUE, $entity->access('update', $this->owner, TRUE)->isAllowed());
    $this->assertEquals(TRUE, $entity->access('delete', $this->owner, TRUE)->isAllowed());
  }
}
