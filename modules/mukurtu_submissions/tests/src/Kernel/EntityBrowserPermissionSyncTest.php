<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\user\Entity\Role;

/**
 * Tests that access to whichever entity browser(s) a bundle's submission
 * form actually uses (e.g. Collection's "Items in Collection") is granted
 * to anonymous/authenticated based on the settings entity's own status
 * and access_level - matching mukurtu_submissions_sync_submission_
 * permission()'s own logic exactly, since Entity Browser gates each
 * browser's modal route behind a distinct "access {id} entity browser
 * pages" permission separate from the field's own rendering.
 *
 * @group mukurtu_submissions
 */
class EntityBrowserPermissionSyncTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * Adds entity_browser on top of the shared base list solely so its
   * config schema is available - setComponent(..., 'entity_browser_entity_
   * reference') below fails the strict schema checker without it. No
   * other test in this module's suite needs it.
   */
  protected static $modules = [
    'field',
    'field_group',
    'file',
    'options',
    'path_alias',
    'node',
    'views',
    'entity_browser',
    'mukurtu_submissions',
  ];

  const PERMISSION = 'access mukurtu_test_browser entity browser pages';

  protected function addEntityBrowserField(): void {
    // Entity Browser's own Permissions::permissions() only registers
    // "access {id} entity browser pages" for browsers that actually exist
    // (and have a route-producing display) - Role::calculateDependencies()
    // silently strips any permission save() doesn't recognize as valid, so
    // the browser referenced below has to be real, not just a settings
    // string, or every grant in this test would be dropped on save().
    EntityBrowser::create([
      'name' => 'mukurtu_test_browser',
      'label' => 'Test Browser',
      'display' => 'modal',
      'display_configuration' => [],
      'selection_display' => 'no_display',
      'selection_display_configuration' => [],
      'widget_selector' => 'single',
      'widget_selector_configuration' => [],
      'widgets' => [],
    ])->save();

    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'submission');
    $display->setComponent('field_test_thing', [
      'type' => 'entity_browser_entity_reference',
      'settings' => ['entity_browser' => 'mukurtu_test_browser'],
    ]);
    $display->save();
  }

  public function testEnabledAnonymousSettingsGrantsBothRoles(): void {
    $this->addEntityBrowserField();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();

    $this->assertTrue(Role::load('anonymous')->hasPermission(static::PERMISSION));
    $this->assertTrue(Role::load('authenticated')->hasPermission(static::PERMISSION));
  }

  public function testAuthenticatedOnlySettingsDoesNotGrantAnonymous(): void {
    $this->addEntityBrowserField();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'authenticated',
    ])->save();

    $this->assertFalse(Role::load('anonymous')->hasPermission(static::PERMISSION));
    $this->assertTrue(Role::load('authenticated')->hasPermission(static::PERMISSION));
  }

  public function testDisabledSettingsGrantsNeitherRole(): void {
    $this->addEntityBrowserField();

    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => FALSE,
      'access_level' => 'anonymous',
    ])->save();

    $this->assertFalse(Role::load('anonymous')->hasPermission(static::PERMISSION));
    $this->assertFalse(Role::load('authenticated')->hasPermission(static::PERMISSION));
  }

  public function testDisablingAfterEnablingRevokesAccess(): void {
    $this->addEntityBrowserField();

    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ]);
    $settings->save();
    $this->assertTrue(Role::load('anonymous')->hasPermission(static::PERMISSION));

    $settings->set('status', FALSE);
    $settings->save();

    $this->assertFalse(Role::load('anonymous')->hasPermission(static::PERMISSION), 'Unlike reviewer permissions, entity browser access tracks the submit permission\'s own lifecycle and should be revoked when the form is disabled.');
  }

  public function testNoEntityBrowserFieldIsANoOp(): void {
    // No addEntityBrowserField() call - the "submission" display has no
    // entity_browser_entity_reference component at all.
    SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ])->save();

    $this->assertFalse(Role::load('anonymous')->hasPermission(static::PERMISSION));
  }

}
