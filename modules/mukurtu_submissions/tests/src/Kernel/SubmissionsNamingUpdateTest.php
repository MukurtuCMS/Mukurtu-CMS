<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Public Submissions" to "Submission Forms" naming cleanup.
 *
 * The rename changed the human-readable titles only. The permission machine
 * names deliberately did not change, since renaming those would silently
 * revoke them from every role that held them, so this asserts the titles
 * resolve under the unchanged machine names.
 */
#[Group('mukurtu_submissions')]
class SubmissionsNamingUpdateTest extends MukurtuSubmissionsKernelTestBase {
  /**
   * The static permissions keep their machine names; only their
   * human-readable titles changed, so admin/people/permissions and any
   * existing role grants keep working unmodified.
   */
  public function testStaticPermissionTitlesUpdated(): void {
    $permissions = $this->container->get('user.permissions')->getPermissions();

    $this->assertArrayHasKey('administer mukurtu submissions', $permissions);
    $this->assertEquals('Administer submission forms', (string) $permissions['administer mukurtu submissions']['title']);

    $this->assertArrayHasKey('review mukurtu submissions', $permissions);
    $this->assertEquals('Review submissions', (string) $permissions['review mukurtu submissions']['title']);
  }
}
