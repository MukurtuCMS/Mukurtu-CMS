<?php

namespace Drupal\Tests\config_pages\Functional;

use Drupal\config_pages\Entity\ConfigPagesType;
use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests ConfigPagesTypeForm functionality.
 *
 * @group config_pages
 */
#[RunTestsInSeparateProcesses]
class ConfigPagesTypeFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['config_pages'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests creating a new config page type through the form.
   */
  public function testConfigPageTypeCreate(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    // Navigate to the add form.
    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->assertSession()->statusCodeEquals(200);

    // Check that the form contains expected fields.
    $this->assertSession()->fieldExists('label');
    $this->assertSession()->fieldExists('id');
    $this->assertSession()->fieldExists('token');
    $this->assertSession()->fieldExists('menu[path]');
    $this->assertSession()->fieldExists('menu[weight]');
    $this->assertSession()->fieldExists('menu[description]');

    // Submit the form.
    $this->submitForm([
      'label' => 'Test Type Created',
      'id' => 'test_type_created',
      'token' => TRUE,
      'menu[path]' => '/test-created-path',
      'menu[weight]' => 5,
      'menu[description]' => 'Test description',
    ], 'Save');

    // Verify the success message.
    $this->assertSession()->pageTextContains('Custom config page type Test Type Created has been added');

    // Verify the entity was created.
    $configPageType = ConfigPagesType::load('test_type_created');
    $this->assertNotNull($configPageType);
    $this->assertEquals('Test Type Created', $configPageType->label());
    $this->assertTrue($configPageType->token);
    $this->assertEquals('/test-created-path', $configPageType->menu['path']);
    $this->assertEquals(5, $configPageType->menu['weight']);
    $this->assertEquals('Test description', $configPageType->menu['description']);
  }

  /**
   * Tests updating an existing config page type.
   */
  public function testConfigPageTypeUpdate(): void {
    // Create an existing config page type.
    $configPageType = ConfigPagesType::create([
      'id' => 'test_update_type',
      'label' => 'Original Label',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/original-path',
        'weight' => 0,
        'description' => 'Original description',
      ],
      'token' => FALSE,
    ]);
    $configPageType->save();

    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    // Navigate to the edit form.
    $this->drupalGet('admin/structure/config_pages/types/manage/test_update_type');
    $this->assertSession()->statusCodeEquals(200);

    // Machine name should be disabled for existing entities.
    $idField = $this->assertSession()->fieldExists('id');
    $this->assertTrue($idField->hasAttribute('disabled'));

    // Update the values.
    $this->submitForm([
      'label' => 'Updated Label',
      'menu[description]' => 'Updated description',
    ], 'Save');

    // Verify the success message.
    $this->assertSession()->pageTextContains('Custom config page type Updated Label has been updated');

    // Reload and verify.
    $configPageType = ConfigPagesType::load('test_update_type');
    $this->assertEquals('Updated Label', $configPageType->label());
    $this->assertEquals('Updated description', $configPageType->menu['description']);
  }

  /**
   * Tests validation that menu path must start with slash.
   */
  public function testMenuPathValidationSlash(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');

    // Submit with a path that doesn't start with slash.
    $this->submitForm([
      'label' => 'Test Invalid Path',
      'id' => 'test_invalid_path',
      'menu[path]' => 'no-slash-path',
    ], 'Save');

    // Should show validation error.
    $this->assertSession()->pageTextContains('Manually entered paths should start with /');

    // Entity should not be created.
    $configPageType = ConfigPagesType::load('test_invalid_path');
    $this->assertNull($configPageType);
  }

  /**
   * Tests validation that menu path must be unique.
   */
  public function testMenuPathValidationUnique(): void {
    // Create a config page type with a menu path.
    $existingType = ConfigPagesType::create([
      'id' => 'existing_type',
      'label' => 'Existing Type',
      'context' => [
        'show_warning' => FALSE,
        'group' => [],
      ],
      'menu' => [
        'path' => '/existing-path',
        'weight' => 0,
        'description' => '',
      ],
      'token' => FALSE,
    ]);
    $existingType->save();

    // Rebuild routes to register the path.
    $this->container->get('router.builder')->rebuild();

    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    // Try to create another type with the same path.
    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->submitForm([
      'label' => 'New Type Same Path',
      'id' => 'new_type_same_path',
      'menu[path]' => '/existing-path',
    ], 'Save');

    // Should show validation error.
    $this->assertSession()->pageTextContains('This menu path already exists');

    // Entity should not be created.
    $configPageType = ConfigPagesType::load('new_type_same_path');
    $this->assertNull($configPageType);
  }

  /**
   * Tests creating config page type without menu path.
   */
  public function testConfigPageTypeWithoutMenuPath(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->submitForm([
      'label' => 'No Menu Path Type',
      'id' => 'no_menu_path_type',
    ], 'Save');

    // Should succeed.
    $this->assertSession()->pageTextContains('Custom config page type No Menu Path Type has been added');

    $configPageType = ConfigPagesType::load('no_menu_path_type');
    $this->assertNotNull($configPageType);
    $this->assertEmpty($configPageType->menu['path']);
  }

  /**
   * Tests context settings in the form.
   */
  public function testContextSettings(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');

    // Check context fields exist.
    $this->assertSession()->fieldExists('context[show_warning]');

    // Submit with context warning enabled.
    $this->submitForm([
      'label' => 'Context Test Type',
      'id' => 'context_test_type',
      'context[show_warning]' => TRUE,
    ], 'Save');

    $configPageType = ConfigPagesType::load('context_test_type');
    $this->assertNotNull($configPageType);
    $this->assertTrue((bool) $configPageType->context['show_warning']);
  }

  /**
   * Tests token setting.
   */
  public function testTokenSetting(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    // Create with token enabled.
    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->submitForm([
      'label' => 'Token Enabled Type',
      'id' => 'token_enabled_type',
      'token' => TRUE,
    ], 'Save');

    $configPageType = ConfigPagesType::load('token_enabled_type');
    $this->assertTrue((bool) $configPageType->token);

    // Create with token disabled.
    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->submitForm([
      'label' => 'Token Disabled Type',
      'id' => 'token_disabled_type',
      'token' => FALSE,
    ], 'Save');

    $configPageType = ConfigPagesType::load('token_disabled_type');
    $this->assertFalse((bool) $configPageType->token);
  }

  /**
   * Tests access denied for users without permission.
   */
  public function testAccessDenied(): void {
    // User without admin permission.
    $user = $this->drupalCreateUser([
      'edit config_pages entity',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests that label is required.
   */
  public function testLabelRequired(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');

    // Try to submit without label.
    $this->submitForm([
      'id' => 'no_label_type',
    ], 'Save');

    // Should show validation error for required field.
    $this->assertSession()->pageTextContains('Label field is required');
  }

  /**
   * Tests redirect after save.
   */
  public function testRedirectAfterSave(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');
    $this->submitForm([
      'label' => 'Redirect Test Type',
      'id' => 'redirect_test_type',
    ], 'Save');

    // Should redirect to the collection page.
    $this->assertSession()->addressEquals('admin/structure/config_pages/types');
  }

  /**
   * Tests menu weight options.
   */
  public function testMenuWeightOptions(): void {
    $user = $this->drupalCreateUser([
      'administer config_pages types',
    ]);
    $this->drupalLogin($user);

    $this->drupalGet('admin/structure/config_pages/types/add');

    // Check that weight field has expected range.
    $weightField = $this->assertSession()->selectExists('menu[weight]');
    $options = $weightField->findAll('css', 'option');

    // Should have options from -50 to 50 (101 options).
    $this->assertCount(101, $options);

    // Test saving with specific weight.
    $this->submitForm([
      'label' => 'Weight Test Type',
      'id' => 'weight_test_type',
      'menu[weight]' => -25,
    ], 'Save');

    $configPageType = ConfigPagesType::load('weight_test_type');
    $this->assertEquals(-25, $configPageType->menu['weight']);
  }

}
