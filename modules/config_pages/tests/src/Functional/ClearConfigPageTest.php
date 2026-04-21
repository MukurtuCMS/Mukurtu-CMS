<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the ConfigPages Type can be cleared.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ClearConfigPageTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['block', 'config_pages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->drupalPlaceBlock('local_tasks_block');

    $configPageType = ConfigPagesType::create([
      'id' => 'config_pages_test_type',
      'label' => 'ConfigPages Test Type Label',
      'context' => [
        'show_warning' => '',
        'group' => [],
      ],
      'menu' => [
        'path' => '/admin/config_pages_test/test_page',
        'weight' => 0,
        'description' => 'Test page for ConfigPages module.',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_test',
      'entity_type' => 'config_pages',
      'type' => 'string',
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'config_pages_test_type',
      'label' => 'Test field',
      'settings' => [],
    ]);
    $field->save();

    $this->container->get('entity_display.repository')
      ->getFormDisplay('config_pages', 'config_pages_test_type')
      ->setComponent('field_test', [
        'type' => 'text_textfield',
        'weight' => 0,
      ])
      ->save();

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Check if Config page type was created successfully.
   */
  public function testConfigPagesClear() {
    // @todo Use this account instead of root when
    // https://www.drupal.org/project/config_pages/issues/3361228 is fixed
    $account = $this->drupalCreateUser([
      'edit config_pages entity',
      'administer config_pages types',
    ]);
    $this->drupalLogin($this->rootUser);

    $this->drupalGet('admin/structure/config_pages');
    $this->assertSession()->statusCodeEquals(200);

    // We edit the page setting a value.
    $this->clickLink('Edit');
    $this->submitForm(['field_test[0][value]' => 'Test value'], 'Save');

    // The value is persisted.
    $this->assertSession()->pageTextContains('ConfigPages Test Type Label has been created.');
    $this->assertSession()->fieldValueEquals('field_test[0][value]', 'Test value');

    $this->submitForm([], 'Clear values');

    // Check the confirmation form shows the label, not the ID.
    $this->assertSession()->pageTextContains('Do you want to clear ConfigPages Test Type Label?');
    $this->assertSession()->pageTextContains('This will reset all field values to their defaults.');
    $this->assertSession()->buttonExists('Clear');

    $this->submitForm([], 'Clear');

    // Check success message and values were cleared.
    $this->assertSession()->pageTextContains('The config page ConfigPages Test Type Label has been cleared.');
    $this->assertSession()->fieldValueEquals('field_test[0][value]', '');
  }

}
