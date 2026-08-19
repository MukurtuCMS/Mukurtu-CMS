<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\mukurtu_submissions\Access\SubmissionAccessCheck;
use Drupal\node\Entity\NodeType;

/**
 * Tests SubmissionAccessCheck::access(), the gate in front of the public
 * submission form route (/submit/{entity_type_id}/{bundle}).
 *
 * @group mukurtu_submissions
 */
class SubmissionAccessCheckTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'node',
    'mukurtu_submissions',
  ];

  /**
   * The bundle used throughout - created fresh per test.
   */
  protected string $bundle = 'access_check_test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    NodeType::create(['type' => $this->bundle, 'name' => 'Access check test'])->save();
  }

  /**
   * Saves a mukurtu_submission_settings entity for $this->bundle.
   */
  protected function createSettings(bool $enabled): void {
    $this->entityTypeManager->getStorage('mukurtu_submission_settings')->create([
      'id' => $this->bundle,
      'label' => 'Access check test',
      'status' => $enabled,
      'target_entity_type_id' => 'node',
      'target_bundle' => $this->bundle,
    ])->save();
  }

  /**
   * Saves a "submission" entity form display for $this->bundle, mirroring
   * what SubmissionAccessCheck requires to exist before granting access.
   */
  protected function createSubmissionFormDisplay(): void {
    EntityFormDisplay::create([
      'targetEntityType' => 'node',
      'bundle' => $this->bundle,
      'mode' => 'submission',
      'status' => TRUE,
    ])->save();
  }

  public function testNoSettingsEntityIsForbidden(): void {
    $access_check = \Drupal::classResolver(SubmissionAccessCheck::class);
    $result = $access_check->access($this->createUser(), 'node', $this->bundle);
    $this->assertTrue($result->isForbidden());
  }

  public function testDisabledSettingsIsForbidden(): void {
    $this->createSettings(FALSE);
    $this->createSubmissionFormDisplay();

    $access_check = \Drupal::classResolver(SubmissionAccessCheck::class);
    $result = $access_check->access($this->createUser(), 'node', $this->bundle);
    $this->assertTrue($result->isForbidden());
  }

  public function testEnabledWithoutSubmissionDisplayIsForbidden(): void {
    $this->createSettings(TRUE);

    $access_check = \Drupal::classResolver(SubmissionAccessCheck::class);
    $account = $this->createUser(["submit node $this->bundle content"]);
    $result = $access_check->access($account, 'node', $this->bundle);
    $this->assertTrue($result->isForbidden());
  }

  public function testEnabledWithDisplayButNoPermissionIsForbidden(): void {
    $this->createSettings(TRUE);
    $this->createSubmissionFormDisplay();

    $access_check = \Drupal::classResolver(SubmissionAccessCheck::class);
    // A user with no permissions at all - not even the dynamic
    // "submit node {bundle} content" permission granted in the success
    // case below.
    $account = $this->createUser();
    $result = $access_check->access($account, 'node', $this->bundle);
    $this->assertTrue($result->isForbidden());
  }

  public function testEnabledWithDisplayAndPermissionIsAllowed(): void {
    $this->createSettings(TRUE);
    $this->createSubmissionFormDisplay();

    $access_check = \Drupal::classResolver(SubmissionAccessCheck::class);
    $account = $this->createUser(["submit node $this->bundle content"]);
    $result = $access_check->access($account, 'node', $this->bundle);
    $this->assertTrue($result->isAllowed());
  }

}
