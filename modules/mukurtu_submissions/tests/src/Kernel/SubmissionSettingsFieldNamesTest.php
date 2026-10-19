<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_submissions\Entity\SubmissionSettings;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that every control in the submission settings fields table is named.
 *
 * The table gives each field a row with an include checkbox, a group select
 * and a weight. The select and the weight have always carried an invisible
 * title; the checkbox did not, so it reached the page with no accessible
 * name and a screen reader announced the whole column as "checkbox, not
 * checked" with nothing to say which field each one governed. On the digital
 * heritage settings page that was 31 unnamed controls (#2430, WCAG 4.1.2).
 *
 * The row's visible name is in a separate label cell, which does not name
 * the control, so the name has to be on the element itself.
 *
 * @see \Drupal\mukurtu_submissions\Form\SubmissionSettingsForm::buildFieldsTableElement()
 */
#[Group('mukurtu_submissions')]
class SubmissionSettingsFieldNamesTest extends MukurtuSubmissionsKernelTestBase {

  /**
   * {@inheritdoc}
   *
   * KernelTestBase merges this with the base class's list. media is needed
   * because the form offers a media type select, and its storage handler is
   * resolved while the form is built.
   */
  protected static $modules = ['image', 'media'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('media');
    $this->installConfig(['media']);

    foreach (['field_one', 'field_two'] as $field_name) {
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
  }

  /**
   * Builds the settings form for the test bundle.
   */
  protected function buildSettingsForm(): array {
    $settings = SubmissionSettings::create([
      'id' => static::TEST_BUNDLE,
      'label' => 'Test settings',
      'target_entity_type_id' => 'node',
      'target_bundle' => static::TEST_BUNDLE,
    ]);
    $settings->save();

    return $this->container->get('entity.form_builder')->getForm($settings, 'edit');
  }

  /**
   * Tests that every per-field control carries an accessible name.
   */
  public function testEveryFieldRowControlIsNamed(): void {
    $form = $this->buildSettingsForm();
    $this->assertArrayHasKey('fields_table', $form, 'The form has a fields table.');

    $checked = 0;
    foreach ($form['fields_table'] as $field_name => $row) {
      if (!is_array($row) || str_starts_with((string) $field_name, '#')) {
        continue;
      }
      foreach (['include', 'group', 'weight'] as $control) {
        if (!isset($row[$control]) || !is_array($row[$control])) {
          continue;
        }
        $checked++;
        $title = $row[$control]['#title'] ?? NULL;
        $this->assertNotNull($title,
          "The '$control' control for '$field_name' has a #title; without one "
          . 'it reaches the page with no accessible name.');
        $this->assertNotSame('', (string) $title,
          "The '$control' control for '$field_name' has a non-empty #title.");
      }
    }

    // Guards against the loop above matching nothing and passing vacuously.
    $this->assertGreaterThan(0, $checked, 'The fields table rendered controls to check.');
  }

  /**
   * Tests that the names are hidden visually rather than omitted.
   *
   * The table already shows each field's name in its own cell, so repeating
   * it beside every control would be noise on screen. #title_display
   * 'invisible' renders the label with the visually-hidden class, which
   * keeps it in the accessibility tree, unlike display:none or
   * visibility:hidden which would drop the name again.
   */
  public function testNamesAreVisuallyHiddenNotRemoved(): void {
    $form = $this->buildSettingsForm();

    foreach ($form['fields_table'] as $field_name => $row) {
      if (!is_array($row) || str_starts_with((string) $field_name, '#')) {
        continue;
      }
      if (isset($row['include']) && is_array($row['include'])) {
        $this->assertSame('invisible', $row['include']['#title_display'] ?? NULL,
          "The include checkbox for '$field_name' hides its title visually "
          . 'rather than dropping it.');
      }
    }
  }

}
