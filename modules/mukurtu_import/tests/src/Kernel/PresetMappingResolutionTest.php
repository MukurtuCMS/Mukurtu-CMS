<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\Form\ImportFileSummaryForm;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\ImportBatchExecutable;

/**
 * Tests that selecting a preset import strategy resolves against the
 * file's actual CSV headers.
 *
 * Regression test: a preset's stored mapping pairs canonical source names
 * (e.g. 'Username') with targets, but a real file's header text can differ
 * while still being an unambiguous match (e.g. 'Name', Drupal's actual
 * field label for that target). Selecting a preset used to store its
 * mapping unchanged, silently leaving such columns unmapped -- both in the
 * "X of Y fields mapped" summary and in the migration actually built for
 * the import, which caused the import to run without ever reading that
 * column. Only manually opening "Customize Settings" and saving (which
 * rebuilds the mapping from the real headers via label/name matching)
 * worked around it.
 */
class PresetMappingResolutionTest extends MukurtuImportTestBase {

  /**
   * Test resolving a preset's mapping against a file with different, but
   * unambiguous, header text produces a complete mapping and a working
   * import, without any manual customize-and-save step.
   */
  public function testPresetMappingResolvesRealHeaders() {
    // A preset mirroring the shipped "User - all fields" strategy's
    // relevant entries: canonical source names that won't literally match
    // this test's file.
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('user');
    $strategy->setTargetBundle('user');
    $strategy->setMapping([
      ['source' => 'Username', 'target' => 'name'],
      ['source' => 'Email', 'target' => 'mail'],
    ]);

    // The file's real headers: 'Name' (Drupal's actual field label for the
    // username field) instead of the preset's canonical 'Username', plus
    // an exact-match 'Email' column.
    $file = $this->createCsvFile([
      ['Name', 'Email'],
      ['presetuser', 'presetuser@example.com'],
    ]);
    $this->assertNotNull($file);

    /** @var \Drupal\mukurtu_import\Form\ImportFileSummaryForm $form */
    $form = ImportFileSummaryForm::create($this->container);

    $reflection = new \ReflectionClass($form);
    $resolveMethod = $reflection->getMethod('resolveMappingForFile');
    $resolveMethod->setAccessible(TRUE);
    $resolveMethod->invoke($form, $strategy, $file->id());

    $mapping = $strategy->getMapping();
    $this->assertCount(2, $mapping);

    $targets_by_source = [];
    foreach ($mapping as $entry) {
      $targets_by_source[$entry['source']] = $entry['target'];
    }
    $this->assertEquals('name', $targets_by_source['Name'] ?? NULL);
    $this->assertEquals('mail', $targets_by_source['Email'] ?? NULL);

    $messageMethod = $reflection->getMethod('getMappedFieldsMessage');
    $messageMethod->setAccessible(TRUE);
    $setImportConfigMethod = $reflection->getMethod('setImportConfig');
    $setImportConfigMethod->setAccessible(TRUE);
    $setImportConfigMethod->invoke($form, $file->id(), $strategy);
    $message = (string) $messageMethod->invoke($form, $file->id());
    $this->assertEquals('2 of 2 importable fields mapped', $message);

    // Confirm the resolved mapping also produces a working import: the
    // migration built from it must actually read the 'Name' column, since
    // that's what the reported bug broke.
    $definition = $strategy->toDefinition($file);
    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $executable = new ImportBatchExecutable(
      $migration,
      new MigrateMessage(),
      $this->keyValue,
      \Drupal::service('datetime.time'),
      \Drupal::service('string_translation'),
      $migration_plugin_manager,
      [],
    );
    $result = $executable->import();
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'presetuser']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertEquals('presetuser@example.com', $user->getEmail());
  }

  /**
   * Test that resolving a preset's mapping drops entries for columns the
   * file doesn't actually have, including an entry targeting the entity's
   * own ID key.
   *
   * Regression test: the full "User - all fields" preset maps a column
   * named 'ID' to the 'uid' target. MukurtuImportStrategy::toDefinition()
   * treats any mapping entry targeting the entity's ID key as authoritative
   * for the migration's row identifier, regardless of whether that column
   * exists in the uploaded file. Applying the preset unmodified to a file
   * that has no ID column made the migration source require a nonexistent
   * 'ID' column on every row, throwing "'ID' is defined as a source ID but
   * has no value" and failing to import anything -- while still reporting
   * only 0 created/updated/failed, not a hard error. Resolving against the
   * file's real headers must drop the unused ID mapping entirely so the
   * migration falls back to its own auto-generated row number.
   */
  public function testPresetMappingDropsUnusedIdColumn() {
    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('user');
    $strategy->setTargetBundle('user');
    $strategy->setMapping([
      ['source' => 'Name', 'target' => 'name'],
      ['source' => 'Display Name', 'target' => 'field_display_name'],
      ['source' => 'Email', 'target' => 'mail'],
      ['source' => 'ID', 'target' => 'uid'],
      ['source' => 'UUID', 'target' => 'uuid'],
    ]);

    // The file has no ID or UUID column at all.
    $file = $this->createCsvFile([
      ['Name', 'Display Name', 'Email'],
      ['idtrapuser', 'ID Trap User', 'idtrapuser@example.com'],
    ]);
    $this->assertNotNull($file);

    $form = ImportFileSummaryForm::create($this->container);
    $reflection = new \ReflectionClass($form);
    $resolveMethod = $reflection->getMethod('resolveMappingForFile');
    $resolveMethod->setAccessible(TRUE);
    $resolveMethod->invoke($form, $strategy, $file->id());

    $targets = array_column($strategy->getMapping(), 'target');
    $this->assertNotContains('uid', $targets);
    $this->assertNotContains('uuid', $targets);

    $definition = $strategy->toDefinition($file);
    $this->assertEquals(['_record_number'], $definition['source']['ids']);

    $migration_plugin_manager = \Drupal::service('plugin.manager.migration');
    $migration = $migration_plugin_manager->createStubMigration($definition);
    $executable = new ImportBatchExecutable(
      $migration,
      new MigrateMessage(),
      $this->keyValue,
      \Drupal::service('datetime.time'),
      \Drupal::service('string_translation'),
      $migration_plugin_manager,
      [],
    );
    $result = $executable->import();
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);

    $users = $this->entityTypeManager->getStorage('user')->loadByProperties(['name' => 'idtrapuser']);
    $user = reset($users);
    $this->assertNotFalse($user);
    $this->assertEquals('idtrapuser@example.com', $user->getEmail());
  }

}
