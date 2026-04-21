<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPages;
use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ConfigPagesForm functionality.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages', 'field', 'text'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The config page type for testing.
   *
   * @var \Drupal\config_pages\Entity\ConfigPagesType
   */
  protected $configPageType;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a config page type.
    $this->configPageType = ConfigPagesType::create([
      'id' => 'test_form_type',
      'label' => 'Test Form Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $this->configPageType->save();

    // Create a text field for the config page.
    $fieldStorage = FieldStorageConfig::create([
      'entity_type' => 'config_pages',
      'field_name' => 'field_test_text',
      'type' => 'text',
    ]);
    $fieldStorage->save();

    $field = FieldConfig::create([
      'field_name' => 'field_test_text',
      'entity_type' => 'config_pages',
      'bundle' => 'test_form_type',
      'label' => 'Test Text Field',
      'required' => FALSE,
    ]);
    $field->save();

    // Configure form display.
    $displayRepository = \Drupal::service('entity_display.repository');
    $displayRepository->getFormDisplay('config_pages', 'test_form_type')
      ->setComponent('field_test_text', [
        'type' => 'text_textfield',
        'weight' => 0,
      ])
      ->save();
  }

  /**
   * Tests creating a new config page through the form.
   */
  public function testConfigPageCreate(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Navigate to the config page edit form.
    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the form contains the expected fields.
    $this->assertSession()->fieldExists('field_test_text[0][value]');
    $this->assertSession()->buttonExists('Save');

    // Submit the form with a value.
    $this->submitForm([
      'field_test_text[0][value]' => 'Test content value',
    ], 'Save');

    // Verify the success message.
    $this->assertSession()->pageTextContains('has been created');

    // Verify the config page was saved.
    $configPages = \Drupal::entityTypeManager()
      ->getStorage('config_pages')
      ->loadByProperties(['type' => 'test_form_type']);
    $this->assertCount(1, $configPages);

    $configPage = reset($configPages);
    $this->assertEquals('Test content value', $configPage->get('field_test_text')->value);
  }

  /**
   * Tests updating an existing config page through the form.
   */
  public function testConfigPageUpdate(): void {
    // Create an existing config page.
    $configPage = ConfigPages::create([
      'type' => 'test_form_type',
      'label' => 'Test Form Type',
      'field_test_text' => 'Initial value',
      'context' => serialize([]),
    ]);
    $configPage->save();

    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Navigate to the config page edit form.
    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Update the value.
    $this->submitForm([
      'field_test_text[0][value]' => 'Updated value',
    ], 'Save');

    // Verify the success message.
    $this->assertSession()->pageTextContains('has been updated');

    // Reload and verify the updated value.
    $configPage = ConfigPages::load($configPage->id());
    $this->assertEquals('Updated value', $configPage->get('field_test_text')->value);
  }

  /**
   * Tests the Clear values button visibility based on permissions.
   */
  public function testClearValuesButtonVisibility(): void {
    // Create an existing config page.
    $configPage = ConfigPages::create([
      'type' => 'test_form_type',
      'label' => 'Test Form Type',
      'field_test_text' => 'Some value',
      'context' => serialize([]),
    ]);
    $configPage->save();

    // User without clear permission.
    $userWithoutPermission = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($userWithoutPermission);

    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonNotExists('Clear values');

    // User with clear permission.
    $userWithPermission = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
      'access config_pages clear values option',
    ]);
    $this->drupalLogin($userWithPermission);

    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->buttonExists('Clear values');
  }

  /**
   * Tests that form has correct CSS class.
   */
  public function testFormCssClass(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the form has the expected CSS class.
    $this->assertSession()->elementExists('css', 'form.config-page-test-form-type-form');
  }

  /**
   * Tests context warning message display.
   */
  public function testContextWarningDisplay(): void {
    // Create a config page type with context warning enabled.
    $configPageTypeWithWarning = ConfigPagesType::create([
      'id' => 'test_warning_type',
      'label' => 'Test Warning Type',
      'context' => [
        'show_warning' => TRUE,
        'group' => [],
      ],
      'menu' => [
        'path' => '',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $configPageTypeWithWarning->save();

    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_warning_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Access the form - without context plugins, no warning should show.
    $this->drupalGet('admin/structure/config_pages/test_warning_type/edit');
    $this->assertSession()->statusCodeEquals(200);
  }

  /**
   * Tests form submission without required fields.
   */
  public function testFormSubmissionEmptyFields(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Submit the form without filling any fields.
    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->submitForm([], 'Save');

    // Should still succeed since field is not required.
    $this->assertSession()->pageTextContains('has been created');
  }

  /**
   * Tests accessing the form without permissions.
   */
  public function testFormAccessDenied(): void {
    // Create user without any config_pages permissions.
    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that label is set from type when not provided.
   */
  public function testLabelFromType(): void {
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
      'edit test_form_type config page entity',
    ]);
    $this->drupalLogin($user);

    // Submit form to create new config page.
    $this->drupalGet('admin/structure/config_pages/test_form_type/edit');
    $this->submitForm([
      'field_test_text[0][value]' => 'Test value',
    ], 'Save');

    // Load the config page and verify label is from type.
    $configPages = \Drupal::entityTypeManager()
      ->getStorage('config_pages')
      ->loadByProperties(['type' => 'test_form_type']);
    $configPage = reset($configPages);

    $this->assertEquals('Test Form Type', $configPage->label());
  }

}
