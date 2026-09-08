<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\node\Entity\NodeType;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_submissions_update_40007(): backfills a disabled default
 * submission form for every content type lacking one on an existing site -
 * previously this only happened via the manual
 * "drush mukurtu-submissions:create-default-forms" command, which nothing
 * in hook_install()/the update path ever invoked, so an upgrading site's
 * other content types never got a form at all.
 */
#[Group('mukurtu_submissions')]
class DefaultFormCreationUpdateHookTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * Calls the update hook directly, the same way update.php would.
   */
  protected function runUpdateHook(): void {
    $this->container->get('module_handler')->loadInclude('mukurtu_submissions', 'install');
    mukurtu_submissions_update_40007();
  }

  public function testBackfillsDisabledFormForBundleLackingOne(): void {
    SubmissionSettings::create([
      'id' => 'digital_heritage',
      'label' => 'Submit a Digital Heritage Item',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'digital_heritage',
    ])->save();

    $this->runUpdateHook();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $settings = $storage->load(static::TEST_BUNDLE);
    $this->assertNotNull($settings);
    $this->assertFalse($settings->status());
  }

  public function testExcludedBundleStillNeverGetsAForm(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->runUpdateHook();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $this->assertEmpty($storage->loadByProperties(['target_bundle' => 'article']));
  }

  public function testRunningTwiceDoesNotDuplicateOrError(): void {
    $this->runUpdateHook();
    $this->runUpdateHook();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $this->assertCount(1, $storage->loadByProperties(['target_bundle' => static::TEST_BUNDLE]));
  }

}
