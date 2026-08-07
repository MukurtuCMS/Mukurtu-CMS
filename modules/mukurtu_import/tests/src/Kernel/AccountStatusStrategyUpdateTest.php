<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_import_update_40033(), which simplifies the 'User - all
 * fields' strategy mapping on existing sites: drops fields hidden from the
 * import target list, and replaces Status/Pending with the unified Account
 * Status target.
 *
 * @see mukurtu_import_update_40033()
 */
#[Group('mukurtu_import')]
class AccountStatusStrategyUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'migrate',
    'mukurtu_import',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_import');
    require_once $module_path . '/mukurtu_import.install';
  }

  /**
   * The update hook drops hidden-field mappings and replaces Status/Pending
   * with a single Account Status entry, leaving other entries untouched.
   */
  public function testUpdateSimplifiesMapping(): void {
    MukurtuImportStrategy::create([
      'id' => 'user_all_fields',
      'label' => 'User - all fields',
      'target_entity_type_id' => 'user',
      'target_bundle' => 'user',
      'mapping' => [
        ['source' => 'Username', 'target' => 'name'],
        ['source' => 'Status', 'target' => 'status'],
        ['source' => 'Pending', 'target' => 'field_pending'],
        ['source' => 'Created', 'target' => 'created'],
        ['source' => 'Timezone', 'target' => 'timezone'],
        ['source' => 'Roles', 'target' => 'roles'],
      ],
      'configuration' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'multivalue_delimiter' => ';',
        'default_format' => 'basic_html',
        'identifier_column' => NULL,
        'local_contexts_delimiter' => '>',
      ],
    ])->save();

    mukurtu_import_update_40033();

    $strategy = MukurtuImportStrategy::load('user_all_fields');
    $targets = array_column($strategy->getMapping(), 'target');

    $this->assertNotContains('status', $targets);
    $this->assertNotContains('field_pending', $targets);
    $this->assertNotContains('created', $targets);
    $this->assertNotContains('timezone', $targets);
    $this->assertContains('account_status', $targets);
    $this->assertContains('name', $targets, 'Untouched mappings must be preserved.');
    $this->assertContains('roles', $targets, 'Untouched mappings must be preserved.');
  }

  /**
   * The update hook is a no-op for a strategy that already has an
   * account_status mapping (e.g. a site the developer already customized).
   */
  public function testUpdateDoesNotDuplicateExistingAccountStatusMapping(): void {
    MukurtuImportStrategy::create([
      'id' => 'user_all_fields',
      'label' => 'User - all fields',
      'target_entity_type_id' => 'user',
      'target_bundle' => 'user',
      'mapping' => [
        ['source' => 'Custom Account Status', 'target' => 'account_status'],
      ],
      'configuration' => [
        'delimiter' => ',',
        'enclosure' => '"',
        'escape' => '\\',
        'multivalue_delimiter' => ';',
        'default_format' => 'basic_html',
        'identifier_column' => NULL,
        'local_contexts_delimiter' => '>',
      ],
    ])->save();

    mukurtu_import_update_40033();

    $strategy = MukurtuImportStrategy::load('user_all_fields');
    $this->assertEquals(
      [['source' => 'Custom Account Status', 'target' => 'account_status']],
      $strategy->getMapping(),
    );
  }

  /**
   * The update hook is a no-op for a site that hasn't created this strategy.
   */
  public function testUpdateIsNoOpForMissingStrategy(): void {
    $this->assertNull(MukurtuImportStrategy::load('user_all_fields'));
    mukurtu_import_update_40033();
    $this->assertNull(MukurtuImportStrategy::load('user_all_fields'));
  }

}
