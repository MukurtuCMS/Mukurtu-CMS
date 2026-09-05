<?php

namespace Drupal\Tests\mukurtu_content_warnings\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_content_warnings\Form\MukurtuContentWarningsSettingsForm;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests accessibility markup on the Content Warnings settings form.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1979
 */
class MukurtuContentWarningsSettingsFormAccessibilityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'image',
    'media',
    'mukurtu_content_warnings',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'system']);

    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();
  }

  /**
   * Creates a taxonomy term in the media_tag vocabulary.
   */
  protected function createMediaTagTerm(string $name): Term {
    $term = Term::create(['vid' => 'media_tag', 'name' => $name]);
    $term->save();
    return $term;
  }

  /**
   * Builds the settings form with a given number of taxonomy warning rows.
   */
  protected function buildFormWithRows(int $num_widgets): array {
    $form_object = MukurtuContentWarningsSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_state->set('num_widgets', $num_widgets);
    return \Drupal::formBuilder()->buildForm($form_object, $form_state);
  }

  /**
   * Tests Remove button labeling and the AJAX status/focus scaffolding.
   */
  public function testTaxonomyWarningsAccessibilityMarkup(): void {
    $term_a = $this->createMediaTagTerm('Sacred');
    $term_b = $this->createMediaTagTerm('Restricted');
    \Drupal::configFactory()->getEditable('mukurtu_content_warnings.settings')->set('taxonomy_warnings', [
      ['term' => $term_a->id(), 'warning' => 'Warning A'],
      ['term' => $term_b->id(), 'warning' => 'Warning B'],
    ])->save();

    $form = $this->buildFormWithRows(2);

    // Configured rows are labeled with their term name, not a row number
    // (row numbers are permanent per-slot indexes that never get renumbered
    // after a removal, so they'd develop misleading gaps - see
    // MukurtuContentWarningsSettingsForm::buildForm()).
    $labels = [];
    foreach ([0, 1] as $i) {
      $label = (string) ($form['taxonomy_warnings'][$i]['actions']['#attributes']['aria-label'] ?? '');
      $this->assertNotSame('', $label, "Row $i Remove button has an aria-label.");
      $labels[] = $label;
    }
    $this->assertSame(array_unique($labels), $labels, 'Remove button aria-labels are distinct per row.');
    $this->assertStringContainsString('Sacred', $labels[0]);
    $this->assertStringContainsString('Restricted', $labels[1]);

    // The live region is a sibling of the taxonomy_warnings fieldset, not
    // nested inside the AJAX-replaced wrapper, so it survives the wrapper
    // swap.
    $this->assertArrayNotHasKey('taxonomy_warnings_status', $form['taxonomy_warnings']);
    $this->assertSame('taxonomy-warnings-status', $form['taxonomy_warnings_status']['#attributes']['id']);
    $this->assertSame('polite', $form['taxonomy_warnings_status']['#attributes']['aria-live']);
    $this->assertSame('true', $form['taxonomy_warnings_status']['#attributes']['aria-atomic']);
    $this->assertContains('visually-hidden', $form['taxonomy_warnings_status']['#attributes']['class']);

    // The Add taxonomy warning button has a deterministic id for JS focus
    // targeting.
    $this->assertSame('taxonomy-warnings-add-button', $form['taxonomy_warnings']['actions']['add_taxonomy_warning']['#attributes']['id']);
  }

  /**
   * Tests that a blank, not-yet-configured row gets a generic label.
   */
  public function testBlankRowFallbackLabel(): void {
    $form = $this->buildFormWithRows(1);
    $label = (string) ($form['taxonomy_warnings'][0]['actions']['#attributes']['aria-label'] ?? '');
    $this->assertSame('Remove empty taxonomy warning row', $label);
  }

  /**
   * Tests that the AJAX callback marks the fieldset as changed.
   */
  public function testTaxonomyWarningsAjaxMarker(): void {
    $form_object = MukurtuContentWarningsSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    $form_state->set('num_widgets', 1);
    $form = \Drupal::formBuilder()->buildForm($form_object, $form_state);

    $result = $form_object->addMoreCallback($form, $form_state);
    $this->assertSame('true', $result['#attributes']['data-warnings-just-changed'] ?? NULL);
  }

}
