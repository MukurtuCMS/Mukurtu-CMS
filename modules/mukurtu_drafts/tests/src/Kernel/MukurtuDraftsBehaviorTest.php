<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_drafts\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\drafts_entity_test\Entity\TestDraftEntity;
use Drupal\user\Entity\User;

/**
 * Tests the MukurtuDraftTrait methods and mukurtu_drafts_entity_view() hook.
 *
 * Covers isDraft(), setDraft(), unsetDraft(), draft status persistence through
 * save/reload, the entity_view CSS class injection, and anonymous-user access.
 *
 * @group mukurtu_drafts
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_drafts')]
class MukurtuDraftsBehaviorTest extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Extends MukurtuKernelTestBase's module list with the drafts-specific
   * modules needed for this test. The full parent list must be included since
   * PHP static property overriding replaces the parent's list entirely.
   */
  protected static $modules = [
    'field',
    'file',
    'filter',
    'image',
    'media',
    'node',
    'og',
    'options',
    'path',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_core',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
    'entity_test',
    'drafts_entity_test',
    'mukurtu_drafts',
  ];

  /**
   * A user who owns the test entities.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $owner;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // entity_test and drafts_entity_test are not part of MukurtuKernelTestBase.
    $this->installEntitySchema('entity_test');
    $this->installEntitySchema('drafts_entity_test');

    $this->owner = User::create(['name' => $this->randomString()]);
    $this->owner->save();
  }

  // ---------------------------------------------------------------------------
  // MukurtuDraftTrait — isDraft / setDraft / unsetDraft
  // ---------------------------------------------------------------------------

  /**
   * A newly created entity is not a draft by default.
   */
  public function testIsDraft_defaultFalse(): void {
    $entity = TestDraftEntity::create(['name' => 'test']);
    $this->assertFalse($entity->isDraft());
  }

  /**
   * setDraft() makes isDraft() return TRUE.
   */
  public function testSetDraft(): void {
    $entity = TestDraftEntity::create(['name' => 'test']);
    $entity->setDraft();
    $this->assertTrue($entity->isDraft());
  }

  /**
   * unsetDraft() returns isDraft() to FALSE after setDraft().
   */
  public function testUnsetDraft(): void {
    $entity = TestDraftEntity::create(['name' => 'test']);
    $entity->setDraft();
    $entity->unsetDraft();
    $this->assertFalse($entity->isDraft());
  }

  /**
   * setDraft() returns the entity for fluent chaining.
   */
  public function testSetDraft_returnsSelf(): void {
    $entity = TestDraftEntity::create(['name' => 'test']);
    $this->assertSame($entity, $entity->setDraft());
  }

  /**
   * unsetDraft() returns the entity for fluent chaining.
   */
  public function testUnsetDraft_returnsSelf(): void {
    $entity = TestDraftEntity::create(['name' => 'test']);
    $entity->setDraft();
    $this->assertSame($entity, $entity->unsetDraft());
  }

  /**
   * Draft status persists through save and reload.
   */
  public function testDraftStatusPersistsAfterSave(): void {
    $entity = TestDraftEntity::create(['name' => 'persistTest']);
    $entity->setDraft();
    $entity->save();

    $reloaded = TestDraftEntity::load($entity->id());
    $this->assertTrue($reloaded->isDraft());
  }

  /**
   * Non-draft status also persists correctly through save and reload.
   */
  public function testNonDraftStatusPersistsAfterSave(): void {
    $entity = TestDraftEntity::create(['name' => 'nonDraftPersist']);
    $entity->unsetDraft();
    $entity->save();

    $reloaded = TestDraftEntity::load($entity->id());
    $this->assertFalse($reloaded->isDraft());
  }

  // ---------------------------------------------------------------------------
  // mukurtu_drafts_entity_view() — CSS class injection
  // ---------------------------------------------------------------------------

  /**
   * A draft entity gets the 'node--unpublished' CSS class added to its build.
   */
  public function testEntityView_draftGetsCssClass(): void {
    $entity = TestDraftEntity::create(['name' => 'draftView']);
    $entity->setDraft();

    $build = [];
    // Required by the hook signature but never called — stub satisfies type hint only.
    $display = $this->createMock(EntityViewDisplayInterface::class);
    mukurtu_drafts_entity_view($build, $entity, $display, 'full');

    $this->assertArrayHasKey('#attributes', $build);
    $this->assertContains('node--unpublished', $build['#attributes']['class']);
  }

  /**
   * A non-draft entity does NOT get the 'node--unpublished' class.
   */
  public function testEntityView_nonDraftNoCssClass(): void {
    $entity = TestDraftEntity::create(['name' => 'nonDraftView']);
    $entity->unsetDraft();

    $build = [];
    // Required by the hook signature but never called — stub satisfies type hint only.
    $display = $this->createMock(EntityViewDisplayInterface::class);
    mukurtu_drafts_entity_view($build, $entity, $display, 'full');

    $classes = $build['#attributes']['class'] ?? [];
    $this->assertNotContains('node--unpublished', $classes);
  }

  // ---------------------------------------------------------------------------
  // hook_entity_access() — anonymous user
  // ---------------------------------------------------------------------------

  /**
   * An anonymous user is forbidden from viewing a draft entity.
   */
  public function testAnonymousForbiddenFromDraftEntity(): void {
    $entity = TestDraftEntity::create(['name' => 'anonTest']);
    $entity->setDraft();
    $entity->setOwner($this->owner);

    $anon = new AnonymousUserSession();
    $this->assertTrue($entity->access('view', $anon, TRUE)->isForbidden());
  }

  /**
   * An anonymous user gets neutral access on a non-draft entity (defers to
   * other access handlers).
   */
  public function testAnonymousNeutralOnNonDraftEntity(): void {
    $entity = TestDraftEntity::create(['name' => 'anonNonDraft']);
    $entity->unsetDraft();
    $entity->setOwner($this->owner);

    $anon = new AnonymousUserSession();
    $this->assertTrue($entity->access('view', $anon, TRUE)->isNeutral());
  }

}
