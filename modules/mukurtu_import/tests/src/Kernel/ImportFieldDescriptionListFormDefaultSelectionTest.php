<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm;

/**
 * Tests that the CSV template download defaults to all fields selected.
 *
 * Regression test for issue #1933: the "Download CSV Template" button on
 * Import Format Description pages produced a CSV with no header row because
 * the field tableselect never pre-checked any rows.
 */
class ImportFieldDescriptionListFormDefaultSelectionTest extends MukurtuImportTestBase {

  /**
   * Every field option should be checked by default when the form builds.
   */
  public function testAllFieldsCheckedByDefault(): void {
    $form = \Drupal::formBuilder()->getForm(ImportFieldDescriptionListForm::class, 'node', 'protocol_aware_content');

    $option_keys = array_keys($form['table']['#options']);
    $this->assertNotEmpty($option_keys, 'The field tableselect has at least one option to select from.');
    $this->assertSame(array_combine($option_keys, $option_keys), $form['table']['#default_value'], 'Every field option is checked by default.');
  }

  /**
   * Submitting the form untouched should download a CSV with every header.
   */
  public function testDefaultSubmissionProducesFullCsvHeaders(): void {
    $form = \Drupal::formBuilder()->getForm(ImportFieldDescriptionListForm::class, 'node', 'protocol_aware_content');
    $option_keys = array_keys($form['table']['#options']);
    $expected_headers = array_map(fn ($key) => (string) $form['table']['#options'][$key]['label'], $option_keys);

    $form_state = new FormState();
    $form_state->setValues([
      'entity_type_id' => 'node',
      'bundle' => 'protocol_aware_content',
      'table' => array_combine($option_keys, $option_keys),
    ]);
    \Drupal::formBuilder()->submitForm(ImportFieldDescriptionListForm::class, $form_state, 'node', 'protocol_aware_content');

    $response = $form_state->getResponse();
    $this->assertNotNull($response, 'Submitting the form produces a downloadable response.');
    $headers = str_getcsv(rtrim($response->getContent(), "\r\n"));
    $this->assertSame($expected_headers, $headers, 'The downloaded CSV contains a header for every field.');
  }

}
