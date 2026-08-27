<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Access;

use Drupal\KernelTests\KernelTestBase;
use Drupal\media\Entity\Media;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests MukurtuProtocolMediaAccessControlHandler's "empty protocol set"
 * fallback - the path exercised by media created with no cultural protocol
 * assigned yet (e.g. mukurtu_submissions' visitor-uploaded media, pending
 * review). Mirrors AccessByProtocolTest::testEmptyorNoProtocols() for
 * nodes, plus the role-permission fallback added specifically for media
 * (which, unlike the node handler, also has to cover 'view' - core's own
 * media access ties unpublished view strictly to ownership with no
 * per-bundle "view any" permission to fall back on).
 */
#[Group('mukurtu_protocol')]
class MediaEmptyProtocolAccessTest extends KernelTestBase {

  use MediaTypeCreationTrait;
  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'image',
    'media',
    'media_test_source',
    'mukurtu_protocol',
    'og',
    'options',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installSchema('file', 'file_usage');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installConfig(['field', 'system', 'image', 'file', 'media']);

    // Burn uid 1 on a throwaway account before any test method's own
    // createUser() calls - the first user created in a fresh kernel test
    // otherwise becomes uid 1, which bypasses every permission check
    // (Drupal's superuser convention), silently defeating the "no
    // permission" assertions below. Mirrors core's own
    // Drupal\Tests\media\Kernel\MediaKernelTestBase::setUp().
    User::create(['name' => 'uid1-placeholder', 'status' => 1])->save();

    // No explicit bundle class is assigned to this media type, so
    // mukurtu_protocol_entity_bundle_info_alter() gives it MukurtuMedia -
    // the same as any real, unconfigured media bundle.
    $this->createMediaType('test', ['id' => 'test']);
  }

  /**
   * Creates an unpublished media entity with no protocols set.
   */
  protected function createEmptyProtocolMedia(int $owner_uid): Media {
    $media = Media::create([
      'bundle' => 'test',
      'uid' => $owner_uid,
      'status' => FALSE,
    ]);
    assert($media instanceof CulturalProtocolControlledInterface);
    $media->setProtocols([]);
    $media->save();
    return $media;
  }

  /**
   * A non-owner with no relevant permission is always forbidden, not just
   * neutral - unlike core's own default (which would fall through to a
   * neutral "no opinion" and let another module decide), protocol-less
   * media is a deliberate lockdown, so the handler must say "forbidden"
   * outright.
   */
  public function testNonOwnerNoPermissionIsForbidden(): void {
    $owner = $this->createUser();
    $stranger = $this->createUser();
    $media = $this->createEmptyProtocolMedia((int) $owner->id());

    foreach (['view', 'update', 'delete'] as $operation) {
      $this->assertTrue(
        $media->access($operation, $stranger, TRUE)->isForbidden(),
        "Non-owner with no permission should be forbidden $operation access."
      );
    }
  }

  /**
   * "Edit any {bundle} media" is sufficient to view or update protocol-less
   * media even when you're not its owner - the fallback this test covers
   * is what lets e.g. a reviewer see a visitor's pending upload.
   */
  public function testNonOwnerWithEditBundlePermissionCanViewAndUpdate(): void {
    $owner = $this->createUser();
    $reviewer = $this->createUser(['edit any test media']);
    $media = $this->createEmptyProtocolMedia((int) $owner->id());

    $this->assertTrue($media->access('view', $reviewer, TRUE)->isAllowed());
    $this->assertTrue($media->access('update', $reviewer, TRUE)->isAllowed());
    // Delete is a separate permission - edit alone shouldn't grant it.
    $this->assertTrue($media->access('delete', $reviewer, TRUE)->isForbidden());
  }

  /**
   * "Delete any {bundle} media" grants delete but not view/update - it's
   * a distinct permission from "edit any", matching the same asymmetry
   * already established by the sibling node access handler.
   */
  public function testNonOwnerWithDeleteBundlePermissionCanOnlyDelete(): void {
    $owner = $this->createUser();
    $remover = $this->createUser(['delete any test media']);
    $media = $this->createEmptyProtocolMedia((int) $owner->id());

    $this->assertTrue($media->access('delete', $remover, TRUE)->isAllowed());
    $this->assertTrue($media->access('view', $remover, TRUE)->isForbidden());
    $this->assertTrue($media->access('update', $remover, TRUE)->isForbidden());
  }

  /**
   * The owner branch (unchanged by this fix) still defers to core's own
   * media access logic rather than an automatic allow - an owner still
   * needs 'view own unpublished media' to view their own unpublished item.
   */
  public function testOwnerStillDefersToCoreAccessLogic(): void {
    $owner = $this->createUser();
    $media = $this->createEmptyProtocolMedia((int) $owner->id());
    $this->assertTrue($media->access('view', $owner, TRUE)->isNeutral());

    $owner_with_permission = $this->createUser(['view own unpublished media']);
    $media2 = $this->createEmptyProtocolMedia((int) $owner_with_permission->id());
    // Reload from storage - a freshly access()-checked entity (e.g. the one
    // rendered on a real review form) is always loaded fresh, never the
    // original in-memory object right after ::create(), and Field API
    // normalizes the "uid" target_id's type consistently once reloaded.
    $storage = \Drupal::entityTypeManager()->getStorage('media');
    $storage->resetCache([$media2->id()]);
    $media2 = $storage->load($media2->id());
    $result = $media2->access('view', $owner_with_permission, TRUE);
    $this->assertTrue($result->isAllowed());
  }

}
