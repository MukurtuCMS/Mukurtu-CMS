<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that the ConfigPages Type can be created.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class CreateConfigPageTypeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

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
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Check if Config page type was created successfully.
   */
  public function testConfigPagesTypeCreate() {
    $account = $this->drupalCreateUser(['administer config_pages types']);
    $this->drupalLogin($account);

    $this->drupalGet('admin/structure/config_pages/types');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('ConfigPages Test Type Label');

  }

  /**
   * Check if field was added to Config page was added successfully.
   */
  public function testConfigPagesTypeAddField() {

    $fieldName = 'config_pages_import_test_field';

    $field_storage = FieldStorageConfig::loadByName('config_pages', $fieldName);
    if (empty($field_storage)) {
      $field_storage = FieldStorageConfig::create([
        'entity_type' => 'config_pages',
        'field_name' => $fieldName,
        'type' => 'text',
      ]);
      $field_storage->save();

      $bundle = 'config_pages_test_type';
      $field = FieldConfig::loadByName('config_pages', $bundle, $fieldName);
      if (empty($field)) {
        FieldConfig::create(
          [
            'field_name' => $fieldName,
            'entity_type' => 'config_pages',
            'bundle' => $bundle,
            'label' => 'Test text field for ConfigPage',
            'required' => FALSE,
            'translatable' => FALSE,
          ]
        )->save();
      }

      $formField = [
        'type' => 'string_textfield',
        'region' => 'content',
        'weight' => 9,
        'settings' => [
          'size' => 60,
        ],
        'third_party_settings' => [],
      ];

      $formDisplayConfig = \Drupal::configFactory()->getEditable('core.entity_form_display.config_pages.config_pages_test_type.default');
      $formDisplayConfig->set('content.' . $fieldName, $formField);
      $formDisplayConfig->save();
    }

    $account = $this->drupalCreateUser(['edit config_pages_test_type config page entity'], 'Vasia', TRUE);
    $this->drupalLogin($account);

    $this->drupalGet('admin/structure/config_pages');
    $this->assertSession()->statusCodeEquals(200);

    // Test that there is an empty reaction rule listing.
    $this->assertSession()->pageTextContains('ConfigPages Test Type Label');
    $this->clickLink('Edit');
    $this->assertSession()->statusCodeEquals(200);
  }

}
