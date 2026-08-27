<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\user\Entity\User;

/**
 * Tests that the "Additional reviewers to notify" autocomplete never
 * offers Anonymous or the hidden submissions service account - neither
 * is a real reviewer a site builder would ever want to notify.
 *
 * @group mukurtu_submissions
 */
class NotifyReviewerUserSelectionTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'user',
  ];

  protected function getReferenceableUids(): array {
    $handler = \Drupal::service('plugin.manager.entity_reference_selection')->getInstance([
      'target_type' => 'user',
      'handler' => 'mukurtu_submissions_notify_reviewer',
    ]);
    return array_keys($handler->getReferenceableEntities()['user'] ?? []);
  }

  public function testAnonymousIsExcluded(): void {
    $this->assertNotContains(0, $this->getReferenceableUids());
  }

  public function testServiceAccountIsExcludedWhenConfigured(): void {
    $service_account = User::create(['name' => 'Submission Forms', 'status' => 0]);
    $service_account->save();
    \Drupal::configFactory()->getEditable('mukurtu_submissions.settings')
      ->set('service_account_uid', (int) $service_account->id())
      ->save();

    $this->assertNotContains((int) $service_account->id(), $this->getReferenceableUids());
  }

  public function testOrdinaryActiveUserIsIncludedWithoutServiceAccountConfigured(): void {
    $account = User::create(['name' => 'a_real_reviewer', 'status' => 1]);
    $account->save();

    $this->assertContains((int) $account->id(), $this->getReferenceableUids());
  }

}
