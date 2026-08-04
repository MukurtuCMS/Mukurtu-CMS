<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;

/**
 * Tests SubmissionFormDisplayManager::seedFieldGroupsFromDefaultForm():
 * converts a bundle's regular "default" form's field_group arrangement
 * into this module's simpler field_groups/field_group_assignments schema.
 *
 * @group mukurtu_submissions
 */
class FieldGroupSeedTest extends MukurtuSubmissionsKernelTestBase {

  protected function setUp(): void {
    parent::setUp();

    foreach (['field_test_thing', 'field_nested_thing', 'field_cultural_protocols'] as $field_name) {
      FieldStorageConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'type' => 'string',
      ])->save();
      FieldConfig::create([
        'field_name' => $field_name,
        'entity_type' => 'node',
        'bundle' => static::TEST_BUNDLE,
        'label' => $field_name,
      ])->save();
    }

    $display = $this->container->get('entity_display.repository')->getFormDisplay('node', static::TEST_BUNDLE, 'default');
    $display->setThirdPartySetting('field_group', 'group_main_tab', [
      'children' => ['group_essentials', 'group_extra'],
      'label' => 'Main Tab',
      'parent_name' => '',
      'format_type' => 'tabs',
      'format_settings' => [],
    ]);
    $display->setThirdPartySetting('field_group', 'group_essentials', [
      'children' => ['title', 'field_test_thing', 'group_nested'],
      'label' => 'Mukurtu Essentials',
      'parent_name' => 'group_main_tab',
      'format_type' => 'tab',
      'format_settings' => ['formatter' => 'open'],
    ]);
    $display->setThirdPartySetting('field_group', 'group_nested', [
      'children' => ['field_nested_thing'],
      'label' => 'Nested Details',
      'parent_name' => 'group_essentials',
      'format_type' => 'details',
      // The "details" formatter uses a boolean `open` key, not the string
      // `formatter` key "tab"/"tabs" use - deliberately different from
      // group_essentials above, to cover both schemas in one test.
      'format_settings' => ['open' => FALSE],
    ]);
    $display->setThirdPartySetting('field_group', 'group_extra', [
      // Its only child is an always-excluded field, so this whole group
      // should be pruned from the result entirely.
      'children' => ['field_cultural_protocols'],
      'label' => 'Extra',
      'parent_name' => 'group_main_tab',
      'format_type' => 'tab',
      'format_settings' => ['formatter' => 'closed'],
    ]);
    $display->save();
  }

  public function testConvertsFieldGroupArrangement(): void {
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ]);

    $this->container->get('mukurtu_submissions.form_display_manager')->seedFieldGroupsFromDefaultForm($settings);

    $groups = [];
    foreach ($settings->getFieldGroups() as $group) {
      $groups[$group['id']] = $group;
    }

    $this->assertArrayNotHasKey('group_main_tab', $groups, 'The outer tabs wrapper should be stripped.');
    $this->assertArrayNotHasKey('group_extra', $groups, 'A group whose only child is excluded should be pruned.');

    $this->assertArrayHasKey('group_essentials', $groups);
    $this->assertSame('', $groups['group_essentials']['parent'], 'Wrapper children get promoted to top-level.');
    $this->assertFalse($groups['group_essentials']['collapsed']);

    $this->assertArrayHasKey('group_nested', $groups);
    $this->assertSame('group_essentials', $groups['group_nested']['parent']);
    $this->assertTrue($groups['group_nested']['collapsed'], 'The "details" formatter uses `open`, not `formatter` - this must map correctly too.');

    $assignments = $settings->getFieldGroupAssignments();
    $this->assertSame('group_essentials', $assignments['title'] ?? NULL);
    $this->assertSame('group_essentials', $assignments['field_test_thing'] ?? NULL);
    $this->assertSame('group_nested', $assignments['field_nested_thing'] ?? NULL);
    $this->assertArrayNotHasKey('field_cultural_protocols', $assignments);
  }

  public function testNoOpWhenDefaultFormHasNoFieldGroups(): void {
    // A second, otherwise-identical bundle whose default form was never
    // organized into groups at all.
    $this->createUngroupedBundle();

    $settings = SubmissionSettings::create([
      'id' => 'ungrouped_test_content',
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => 'ungrouped_test_content',
    ]);

    $this->container->get('mukurtu_submissions.form_display_manager')->seedFieldGroupsFromDefaultForm($settings);

    $this->assertSame([], $settings->getFieldGroups());
    $this->assertSame([], $settings->getFieldGroupAssignments());
  }

  protected function createUngroupedBundle(): void {
    \Drupal\node\Entity\NodeType::create(['type' => 'ungrouped_test_content', 'name' => 'Ungrouped'])->save();
  }

  public function testPruneExcludedFieldsRemovesDeadAssignmentAndEmptyGroup(): void {
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'field_groups' => [
        ['id' => 'group_essentials', 'label' => 'Essentials', 'collapsed' => FALSE, 'parent' => ''],
        ['id' => 'group_related', 'label' => 'Related Content', 'collapsed' => TRUE, 'parent' => ''],
      ],
      'field_group_assignments' => [
        'title' => 'group_essentials',
        // Not a real field on this bundle - pruneExcludedFields() only
        // needs to recognize the name as EXCLUDED_FIELDS, not that it
        // exists, since it's cleaning up config that was saved before the
        // field was added to that list.
        'field_related_content' => 'group_related',
      ],
    ]);
    $settings->save();

    $changed = $this->container->get('mukurtu_submissions.form_display_manager')->pruneExcludedFields($settings);

    $this->assertTrue($changed);
    $assignments = $settings->getFieldGroupAssignments();
    $this->assertArrayNotHasKey('field_related_content', $assignments);
    $this->assertSame('group_essentials', $assignments['title'] ?? NULL);

    $groups = array_column($settings->getFieldGroups(), NULL, 'id');
    $this->assertArrayNotHasKey('group_related', $groups, 'Left with no fields once field_related_content is removed - should be pruned.');
    $this->assertArrayHasKey('group_essentials', $groups);
  }

  public function testPruneExcludedFieldsNoOpWhenNothingToRemove(): void {
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'field_groups' => [
        ['id' => 'group_essentials', 'label' => 'Essentials', 'collapsed' => FALSE, 'parent' => ''],
      ],
      'field_group_assignments' => ['title' => 'group_essentials'],
    ]);
    $settings->save();

    $changed = $this->container->get('mukurtu_submissions.form_display_manager')->pruneExcludedFields($settings);

    $this->assertFalse($changed);
    $this->assertSame('group_essentials', $settings->getFieldGroupAssignments()['title'] ?? NULL);
  }

  public function testPruneExcludedFieldsPrunesAlreadyEmptyGroupWithNoLiveExcludedAssignment(): void {
    // The empty group was left behind by some earlier, partial cleanup -
    // there's no currently-excluded field pointing at it to trigger on,
    // but it should still go: an empty group is exactly what
    // pruneExcludedFields() exists to clean up, regardless of how it got
    // that way.
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
      'field_groups' => [
        ['id' => 'group_essentials', 'label' => 'Essentials', 'collapsed' => FALSE, 'parent' => ''],
        ['id' => 'group_empty', 'label' => 'Already Empty', 'collapsed' => TRUE, 'parent' => ''],
      ],
      'field_group_assignments' => ['title' => 'group_essentials'],
    ]);
    $settings->save();

    $changed = $this->container->get('mukurtu_submissions.form_display_manager')->pruneExcludedFields($settings);

    $this->assertTrue($changed);
    $groups = array_column($settings->getFieldGroups(), NULL, 'id');
    $this->assertArrayNotHasKey('group_empty', $groups);
    $this->assertArrayHasKey('group_essentials', $groups);
  }

}
