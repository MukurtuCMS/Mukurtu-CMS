<?php

namespace Drupal\Tests\features_ui\Functional;

use Drupal\features\FeaturesBundleInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests configuring bundles.
 *
 * @group features_ui
 */
class FeaturesBundleUiTest extends BrowserTestBase {

  /**
   * The variable.
   *
   * @var mixed
   * @todo Remove the disabled strict config schema checking.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['block', 'features', 'features_ui'];

  /**
   * The features bundle storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $bundleStorage;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->bundleStorage = \Drupal::entityTypeManager()->getStorage('features_bundle');

    $admin_user = $this->createUser([
      'administer site configuration',
      'export configuration',
      'administer modules',
    ]);
    $this->drupalLogin($admin_user);
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Get the default features bundle.
   *
   * @return \Drupal\features\FeaturesBundleInterface
   *   The features bundle.
   */
  protected function defaultBundle() {
    return $this->bundleStorage->load(FeaturesBundleInterface::DEFAULT_BUNDLE);
  }

  /**
   * Completely remove a features assignment method from the bundle.
   *
   * @param string $method_id
   *   The assignment method ID.
   */
  protected function removeAssignment($method_id) {
    $bundle = $this->defaultBundle();
    $assignments = $bundle->get('assignments');
    unset($assignments[$method_id]);
    $bundle->set('assignments', $assignments);
    $bundle->save();
  }

  /**
   * Tests configuring an assignment.
   */
  public function testAssignmentConfigure() {
    // Check initial values.
    $settings = $this->defaultBundle()->getAssignmentSettings('exclude');
    $this->assertTrue(isset($settings['types']['config']['features_bundle']), 'Excluding features_bundle');
    $this->assertFalse(isset($settings['types']['config']['system_simple']), 'Not excluding system_simple');
    $this->assertFalse(isset($settings['types']['config']['user_role']), 'Not excluding user_role');
    $this->assertTrue($settings['curated'], 'Excluding curated items');
    $this->assertTrue($settings['module']['namespace'], 'Excluding by namespace');

    // Check initial form.
    $this->drupalGet('admin/config/development/features/bundle/_exclude/default');
    $this->assertSession()->checkboxChecked('edit-types-config-features-bundle');
    $this->assertSession()->checkboxNotChecked('edit-types-config-system-simple');
    $this->assertSession()->checkboxNotChecked('edit-types-config-user-role');
    $this->assertSession()->checkboxChecked('edit-curated');
    $this->assertSession()->checkboxChecked('edit-module-namespace');

    // Configure the form.
    $this->submitForm([
      'types[config][system_simple]' => TRUE,
      'types[config][user_role]' => FALSE,
      'curated' => TRUE,
      'module[namespace]' => FALSE,
    ], 'Save settings');

    // Check form results.
    $this->drupalGet('admin/config/development/features/bundle/_exclude/default');
    $this->assertSession()->checkboxChecked('edit-types-config-features-bundle');
    $this->assertSession()->checkboxChecked('edit-types-config-system-simple');
    $this->assertSession()->checkboxNotChecked('edit-types-config-user-role');
    $this->assertSession()->checkboxChecked('edit-curated');
    $this->assertSession()->checkboxNotChecked('edit-module-namespace');

    // Check final values.
    $settings = $this->defaultBundle()->getAssignmentSettings('exclude');
    $this->assertTrue(isset($settings['types']['config']['features_bundle']), 'Saved, excluding features_bundle');
    $this->assertTrue(isset($settings['types']['config']['system_simple']), 'Saved, excluding system_simple');
    $this->assertFalse(isset($settings['types']['config']['user_role']), 'Saved, not excluding user_role');
    $this->assertTrue($settings['curated'], 'Saved, excluding curated items');
    $this->assertFalse($settings['module']['namespace'], 'Saved, not excluding by namespace');
  }

  /**
   * Tests configuring an assignment that didn't exist before.
   */
  public function testNewAssignmentConfigure() {
    $this->removeAssignment('exclude');

    // Is it really removed?
    $all_settings = $this->defaultBundle()->getAssignmentSettings();
    $this->assertFalse(isset($all_settings['exclude']), 'Exclude plugin is unknown');

    // Can still get settings.
    $settings = $this->defaultBundle()->getAssignmentSettings('exclude');
    $this->assertFalse($settings['enabled'], 'Disabled exclude plugin');
    $this->assertFalse(isset($settings['types']['config']['features_bundle']), 'Not excluding features_bundle');
    $this->assertFalse(isset($settings['types']['config']['system_simple']), 'Not excluding system_simple');
    $this->assertFalse(isset($settings['types']['config']['user_role']), 'Not excluding user_role');
    $this->assertFalse($settings['curated'], 'Not excluding curated items');
    $this->assertFalse($settings['module']['namespace'], 'Not excluding by namespace');

    // Can we visit the config page with no settings?
    $this->drupalGet('admin/config/development/features/bundle/_exclude/default');
    $this->assertSession()->checkboxNotChecked('edit-types-config-features-bundle');
    $this->assertSession()->checkboxNotChecked('edit-types-config-system-simple');
    $this->assertSession()->checkboxNotChecked('edit-types-config-user-role');
    $this->assertSession()->checkboxNotChecked('edit-curated');
    $this->assertSession()->checkboxNotChecked('edit-module-namespace');

    // Can we enable the method?
    $this->drupalGet('admin/config/development/features/bundle');
    $this->assertSession()->checkboxNotChecked('edit-enabled-exclude');
    $this->submitForm([
      'enabled[exclude]' => TRUE,
    ], 'Save settings');
    $this->assertSession()->checkboxChecked('edit-enabled-exclude');

    // Check new settings.
    $settings = $this->defaultBundle()->getAssignmentSettings('exclude');
    $this->assertTrue($settings['enabled'], 'Enabled exclude plugin');
    $this->assertFalse(isset($settings['types']['config']['features_bundle']), 'Not excluding features_bundle');
    $this->assertFalse(isset($settings['types']['config']['system_simple']), 'Not excluding system_simple');
    $this->assertFalse(isset($settings['types']['config']['user_role']), 'Not excluding user_role');
    $this->assertFalse($settings['curated'], 'Not excluding curated items');
    $this->assertFalse($settings['module']['namespace'], 'Not excluding by namespace');

    // Can we run assignment with no settings?
    $this->drupalGet('admin/config/development/features');
    $this->drupalGet('admin/config/development/features/bundle/_exclude/default');

    // Can we configure the method?
    $this->submitForm([
      'types[config][system_simple]' => TRUE,
      'types[config][user_role]' => FALSE,
      'curated' => TRUE,
      'module[namespace]' => FALSE,
    ], 'Save settings');

    // Check form results.
    $this->drupalGet('admin/config/development/features/bundle/_exclude/default');
    $this->assertSession()->checkboxNotChecked('edit-types-config-features-bundle');
    $this->assertSession()->checkboxChecked('edit-types-config-system-simple');
    $this->assertSession()->checkboxNotChecked('edit-types-config-user-role');
    $this->assertSession()->checkboxChecked('edit-curated');
    $this->assertSession()->checkboxNotChecked('edit-module-namespace');

    // Check final values.
    $settings = $this->defaultBundle()->getAssignmentSettings('exclude');
    $this->assertFalse(isset($settings['types']['config']['features_bundle']), 'Saved, not excluding features_bundle');
    $this->assertTrue(isset($settings['types']['config']['system_simple']), 'Saved, excluding system_simple');
    $this->assertFalse(isset($settings['types']['config']['user_role']), 'Saved, not excluding user_role');
    $this->assertTrue($settings['curated'], 'Saved, excluding curated items');
    $this->assertFalse($settings['module']['namespace'], 'Saved, not excluding by namespace');
  }

}
