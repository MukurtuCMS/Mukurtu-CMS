<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_content_warnings\Kernel;

use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_content_warnings\Form\MukurtuContentWarningsSettingsForm;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests that the settings form keeps every configured tag warning.
 *
 * The form used to render one row on first load whatever the config held,
 * so saving it deleted every tag warning after the first.
 */
class ContentWarningsSettingsFormRowsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
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
   * The configured tag warnings.
   */
  protected array $warnings = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'system']);

    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();
    // The form's view mode checkboxes need at least one media view mode.
    EntityViewMode::create(['id' => 'media.full', 'targetEntityType' => 'media', 'label' => 'Full'])->save();
    foreach (['Ceremony', 'Funeral', 'Burial'] as $name) {
      $term = Term::create(['vid' => 'media_tag', 'name' => $name]);
      $term->save();
      $this->warnings[] = ['term' => (int) $term->id(), 'warning' => "Shows a $name."];
    }
  }

  /**
   * A first load shows one row per configured warning, with its values.
   */
  public function testEveryConfiguredWarningHasARow(): void {
    $this->config('mukurtu_content_warnings.settings')->set('taxonomy_warnings', $this->warnings)->save();
    $form = $this->buildFirstLoad();

    foreach ($this->warnings as $i => $warning) {
      $this->assertEquals($warning['term'], $form['taxonomy_warnings'][$i]['term']['#default_value'], "Row $i term.");
      $this->assertSame($warning['warning'], $form['taxonomy_warnings'][$i]['text']['#default_value'], "Row $i text.");
    }
    $this->assertArrayNotHasKey(3, $form['taxonomy_warnings']);
  }

  /**
   * With no tag warnings, a first load shows one blank row.
   */
  public function testNoWarningsShowsOneBlankRow(): void {
    $form = $this->buildFirstLoad();
    $this->assertNull($form['taxonomy_warnings'][0]['term']['#default_value']);
    $this->assertArrayNotHasKey(1, $form['taxonomy_warnings']);
  }

  /**
   * Saving the form unchanged keeps every tag warning.
   */
  public function testSavingKeepsEveryWarning(): void {
    $this->config('mukurtu_content_warnings.settings')->set('taxonomy_warnings', $this->warnings)->save();

    // Submit what a browser sends: one value set per rendered row.
    $rows = [];
    foreach ($this->warnings as $i => $warning) {
      $rows[$i] = ['term' => $warning['term'], 'text' => $warning['warning']];
    }
    $form_state = (new FormState())->setValues([
      'people_warnings' => [
        'toggle' => FALSE,
        'single_person_text' => '[name] is deceased.',
        'multiple_people_text' => 'The following people are deceased: [names]',
      ],
      'view_mode_warnings' => ['view_modes' => ['full' => 'full']],
      'taxonomy_warnings' => $rows,
      'op' => 'Save configuration',
    ]);
    \Drupal::formBuilder()->submitForm(MukurtuContentWarningsSettingsForm::class, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $saved = $this->config('mukurtu_content_warnings.settings')->get('taxonomy_warnings');
    $this->assertCount(3, $saved);
    $this->assertEquals(array_column($this->warnings, 'term'), array_column($saved, 'term'));
  }

  /**
   * Builds the form as a fresh page load does, with no row count preset.
   */
  protected function buildFirstLoad(): array {
    $form_object = MukurtuContentWarningsSettingsForm::create(\Drupal::getContainer());
    $form_state = new FormState();
    return \Drupal::formBuilder()->buildForm($form_object, $form_state);
  }

}
