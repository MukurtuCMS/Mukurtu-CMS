<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_submissions\Commands\MukurtuSubmissionsCommands;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\node\Entity\NodeType;
use Drush\Log\DrushLoggerManager;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "create-default-forms" Drush command: bulk-creates a disabled,
 * all-fields-included settings entity for every bundle lacking one, and
 * leaves already-configured bundles untouched.
 */
#[Group('mukurtu_submissions')]
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

  public function testLabelOverrideAppliesToPersonAndPlace(): void {
    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();
    NodeType::create(['type' => 'place', 'name' => 'Place'])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $this->assertEquals('Submit a Person Record', $storage->load('person')->label());
    $this->assertEquals('Submit a Place Record', $storage->load('place')->label());
  }

  public function testBackfillsGroupsForExistingUngroupedEntity(): void {
    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'default');
    $display->setThirdPartySetting('field_group', 'group_essentials', [
      'children' => ['title'],
      'label' => 'Essentials',
      'parent_name' => '',
      'format_type' => 'details',
      'format_settings' => ['open' => TRUE],
    ]);
    $display->save();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Already configured',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $groups = $storage->load(static::TEST_BUNDLE)->getFieldGroups();
    $this->assertNotEmpty($groups);
    $this->assertSame('Essentials', reset($groups)['label']);
  }

  public function testNeverBackfillsDigitalHeritageGroups(): void {
    NodeType::create(['type' => 'digital_heritage', 'name' => 'Digital Heritage Item'])->save();
    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', 'digital_heritage', 'default');
    $display->setThirdPartySetting('field_group', 'group_essentials', [
      'children' => ['title'],
      'label' => 'Essentials',
      'parent_name' => '',
      'format_type' => 'details',
      'format_settings' => ['open' => TRUE],
    ]);
    $display->save();

    SubmissionSettings::create([
      'id' => 'digital_heritage',
      'label' => 'Submit a Digital Heritage Item',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'digital_heritage',
    ])->save();

    $this->createCommand()->createDefaultForms();

    $storage = $this->container->get('entity_type.manager')->getStorage('mukurtu_submission_settings');
    $this->assertSame([], $storage->load('digital_heritage')->getFieldGroups());
  }

}
