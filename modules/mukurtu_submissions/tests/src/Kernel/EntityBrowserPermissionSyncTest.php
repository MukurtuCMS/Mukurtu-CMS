<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that access to whichever entity browser(s) a bundle's submission
 * form actually uses (e.g. Collection's "Items in Collection") is granted
 * to anonymous/authenticated based on the settings entity's own status
 * and access_level - matching mukurtu_submissions_sync_submission_
 * permission()'s own logic exactly, since Entity Browser gates each
 * browser's modal route behind a distinct "access {id} entity browser
 * pages" permission separate from the field's own rendering.
 */
#[Group('mukurtu_submissions')]
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

  /**
   * Two bundles can share the same entity browser on their own "submission"
   * displays. Disabling one bundle's form must not revoke access the
   * other, still-enabled bundle's form still needs - the sync used to
   * decide revocation solely from the settings entity being saved, with
   * no regard for whether some other enabled bundle also depends on the
   * same browser.
   */
  public function testDisablingOneBundleDoesNotRevokeAccessAnotherBundleStillNeeds(): void {
    $this->addEntityBrowserField();

    $other_bundle = static::TEST_BUNDLE . '_2';
    NodeType::create(['type' => $other_bundle, 'name' => 'Submission Test Content 2'])->save();
    $other_display = $this->container->get('entity_display.repository')->getFormDisplay('node', $other_bundle, 'submission');
    $other_display->setComponent('field_test_thing', [
      'type' => 'entity_browser_entity_reference',
      'settings' => ['entity_browser' => 'mukurtu_test_browser'],
    ]);
    $other_display->save();

    $settings_a = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings A',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ]);
    $settings_a->save();

    $settings_b = SubmissionSettings::create([
      'id' => $other_bundle,
      'label' => 'Test settings B',
      'target_entity_type_id' => 'node',
      'target_bundle' => $other_bundle,
      'status' => TRUE,
      'access_level' => 'anonymous',
    ]);
    $settings_b->save();

    $this->assertTrue(Role::load('anonymous')->hasPermission(static::PERMISSION));

    // Disabling bundle A's form must not revoke access bundle B's
    // still-enabled form still needs.
    $settings_a->set('status', FALSE);
    $settings_a->save();

    $this->assertTrue(Role::load('anonymous')->hasPermission(static::PERMISSION), "Bundle B still needs this browser - disabling bundle A's form must not revoke it.");
    $this->assertTrue(Role::load('authenticated')->hasPermission(static::PERMISSION));

    // Now disable B too - nothing needs the browser any more, so it
    // should finally be revoked.
    $settings_b->set('status', FALSE);
    $settings_b->save();

    $this->assertFalse(Role::load('anonymous')->hasPermission(static::PERMISSION));
    $this->assertFalse(Role::load('authenticated')->hasPermission(static::PERMISSION));
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
