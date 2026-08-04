<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_submissions\Commands\MukurtuSubmissionsCommands;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\node\Entity\NodeType;
use Drush\Log\DrushLoggerManager;

/**
 * Tests the "create-default-forms" Drush command: bulk-creates a disabled,
 * all-fields-included settings entity for every bundle lacking one, and
 * leaves already-configured bundles untouched.
 *
 * @group mukurtu_submissions
 */
class DefaultFormCreationTest extends MukurtuSubmissionsKernelTestBase {

  protected function createCommand(): MukurtuSubmissionsCommands {
    $command = MukurtuSubmissionsCommands::create($this->container);
    // Outside a real Drush bootstrap nothing ever calls setLogger() on the
    // command, so DrushCommands::logger() returns NULL and
    // createDefaultForms()'s $this->logger()->notice()/success() calls
    // fatal. A real Drush invocation always has one; this stands in for it.
    $command->setLogger(new DrushLoggerManager());
    return $command;
  }

  public function testCreatesDisabledFormWithFieldsIncluded(): void {
    FieldStorageConfig::create([
      'field_name' => 'field_test_thing',
      'entity_type' => 'node',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_test_thing',
      'entity_type' => 'node',
      'bundle' => static::TEST_BUNDLE,
      'label' => 'Test Thing',
    ])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $settings = $storage->load(static::TEST_BUNDLE);
    $this->assertNotNull($settings);
    $this->assertFalse($settings->status());

    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $this->assertNotNull($display->getComponent('field_test_thing'));
    $this->assertNull($display->getComponent('uid'));
  }

  public function testSkipsBundleThatAlreadyHasSettings(): void {
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Already configured',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $matches = $storage->loadByProperties(['target_bundle' => static::TEST_BUNDLE]);
    $this->assertCount(1, $matches);
    $this->assertEquals('Already configured', reset($matches)->label());
  }

  public function testExcludedBundlesNeverGetAForm(): void {
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $this->assertEmpty($storage->loadByProperties(['target_bundle' => 'article']));
  }

}
