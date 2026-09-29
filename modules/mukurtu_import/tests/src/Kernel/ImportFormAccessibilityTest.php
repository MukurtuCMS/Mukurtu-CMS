<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;
use Drupal\mukurtu_import\Form\CustomStrategyFromFileForm;

/**
 * Tests the accessibility affordances on the import wizard's AJAX forms.
 *
 * Both of these forms rewrite content in place over AJAX. The summary form
 * swaps a file's mapping summary when a template is chosen; the customize
 * form rewrites the Sub-type radios and every target dropdown when Type
 * changes. Neither told the user anything had happened, and the summary
 * form's per-row select and button carried no per-row accessible name, so
 * with several files uploaded they were indistinguishable.
 *
 * @see \Drupal\mukurtu_import\Form\ImportFileSummaryForm
 * @see \Drupal\mukurtu_import\Form\CustomStrategyFromFileForm
 */
class ImportFormAccessibilityTest extends MukurtuImportTestBase {

  /**
   * Builds the summary form with one CSV file registered in the wizard.
   *
   * @return array
   *   [$form, $fid, $filename, $form_object].
   */
  private function buildSummaryFormForOneFile(): array {
    $file = $this->createCsvFile([['Title'], ['x']]);

    $form_object = TestImportFileSummaryForm::create($this->container);
    $form_object->testMetadataFids = [$file->id()];

    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping([['source' => 'Title', 'target' => 'title']]);

    $form_object->setImportConfig($file->id(), $strategy);
    $form_object->setMetadataFileWeights([$file->id() => 0]);

    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state);

    return [$form, $file->id(), $file->getFilename(), $form_object];
  }

  /**
   * Every row's mapping select carries its own file-specific name.
   *
   * The visible "Import Settings" column header is not programmatically
   * associated with the cell's select, so without a #title the control has
   * no accessible name at all - and with several files uploaded, every row
   * presents an identical anonymous combobox.
   */
  public function testMappingSelectIsLabelledPerFile(): void {
    [$form, $fid, $filename] = $this->buildSummaryFormForOneFile();

    $select = $form['table'][$fid]['mapping'];
    $this->assertArrayHasKey('#title', $select, 'The mapping select has a title.');
    $this->assertSame("Import settings for {$filename}", (string) $select['#title']);
    $this->assertSame('invisible', $select['#title_display'], 'The label is hidden visually, not removed.');
  }

  /**
   * The summary text beside the select is associated with it.
   */
  public function testMappingSelectIsDescribedByItsSummary(): void {
    [$form, $fid] = $this->buildSummaryFormForOneFile();

    $this->assertSame(
      "mapping-summary-{$fid}",
      $form['table'][$fid]['mapping']['#attributes']['aria-describedby']
    );
    // The referenced element must actually be rendered by this row, or the
    // association dangles.
    $this->assertStringContainsString(
      "id=\"mapping-summary-{$fid}\"",
      (string) $form['table'][$fid]['mapping']['#suffix']
    );
  }

  /**
   * Each row's "Customize Settings" button names the file it acts on.
   *
   * The visible text is identical in every row by design; the accessible
   * name is what has to disambiguate them.
   */
  public function testCustomizeButtonNamesItsFile(): void {
    [$form, $fid, $filename] = $this->buildSummaryFormForOneFile();

    $button = $form['table'][$fid]['edit'];
    $this->assertSame('Customize Settings', (string) $button['#value'], 'The visible label is unchanged.');
    $this->assertSame(
      "Customize Settings for {$filename}",
      (string) $button['#attributes']['aria-label']
    );
  }

  /**
   * Choosing a different template announces the summary it just rewrote.
   */
  public function testMappingChangeIsAnnounced(): void {
    [$form, $fid, $filename, $form_object] = $this->buildSummaryFormForOneFile();

    $form_state = new FormState();
    $form_state->setValue('table', [$fid => ['mapping' => 'custom']]);
    $form_state->setTriggeringElement(['#parents' => ['table', $fid, 'mapping']]);

    $response = $form_object->mappingChangeAjaxCallback($form, $form_state);

    $announcements = $this->getAnnouncements($response);
    $this->assertCount(1, $announcements, 'Exactly one announcement is made.');
    $this->assertStringContainsString("Import settings for {$filename} updated", $announcements[0]);
    // The announcement carries the same information the sighted user reads
    // from the swapped-in summary, not just "updated".
    $this->assertStringContainsString('importable fields mapped', $announcements[0]);
  }

  /**
   * Changing Type announces the Sub-type reset and the remapped columns.
   *
   * This is the largest silent rewrite in the wizard: one radio click
   * re-populates the Sub-type radios and every target dropdown in the
   * mapping table, which on a wide CSV is dozens of controls.
   */
  public function testEntityTypeChangeIsAnnounced(): void {
    $file = $this->createCsvFile([['Title', 'Body'], ['x', 'y']]);

    [$form_object, $form, $form_state] = $this->buildCustomStrategyForm($file);
    $form_state->setValue('entity_type_id', 'node');

    $response = $form_object->entityTypeChangeAjaxCallback($form, $form_state);

    $announcements = $this->getAnnouncements($response);
    $this->assertCount(1, $announcements);
    $this->assertStringContainsString('Type changed to', $announcements[0]);
    $this->assertStringContainsString('Sub-type reset to', $announcements[0]);
    $this->assertStringContainsString('2 column mappings updated', $announcements[0]);
  }

  /**
   * Changing Sub-type announces the remapped columns.
   */
  public function testBundleChangeIsAnnounced(): void {
    $file = $this->createCsvFile([['Title', 'Body'], ['x', 'y']]);

    [$form_object, $form, $form_state] = $this->buildCustomStrategyForm($file);
    $form_state->setValue('entity_type_id', 'node');
    $form_state->setValue('bundle', 'protocol_aware_content');

    $response = $form_object->bundleChangeAjaxCallback($form, $form_state);

    $announcements = $this->getAnnouncements($response);
    $this->assertCount(1, $announcements);
    $this->assertStringContainsString('Sub-type changed to', $announcements[0]);
    $this->assertStringContainsString('2 column mappings updated', $announcements[0]);
  }

  /**
   * Builds the customize form through the real form builder.
   *
   * Deliberately calls buildForm() rather than routing through the form
   * builder: the callbacks index into $form['mappings'] by delta, which a
   * fully processed form no longer exposes that way.
   *
   * @param \Drupal\file\FileInterface $file
   *   The CSV being mapped.
   *
   * @return array
   *   [$form_object, $form, $form_state].
   */
  private function buildCustomStrategyForm($file): array {
    $form_object = CustomStrategyFromFileForm::create($this->container);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, $file);
    $form_state->setValue('fid', $file->id());

    // entityTypeChangeAjaxCallback() renders the Sub-type radios into a
    // ReplaceCommand, and rendering reads three keys that FormBuilder adds
    // during element processing rather than buildForm(). Supply the same
    // defaults it would, so the render does not emit warnings for state
    // that is always present in a real request.
    $form['bundle'] += [
      '#id' => 'edit-bundle',
      '#title_display' => 'before',
      '#description_display' => 'after',
    ];

    return [$form_object, $form, $form_state];
  }

  /**
   * Extracts the text of every AnnounceCommand in an AJAX response.
   *
   * @param \Drupal\Core\Ajax\AjaxResponse $response
   *   The response to inspect.
   *
   * @return string[]
   *   The announced strings, in order.
   */
  private function getAnnouncements($response): array {
    $texts = [];
    foreach ($response->getCommands() as $command) {
      if (($command['command'] ?? NULL) === 'announce') {
        $texts[] = (string) $command['text'];
      }
    }
    return $texts;
  }

}
