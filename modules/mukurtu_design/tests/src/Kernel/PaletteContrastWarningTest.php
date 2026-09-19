<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the design settings form warns rather than blocks.
 *
 * Separate from PaletteContrastAnalyzerTest, which covers the analysis
 * itself. This covers the wiring, which is where the first real bug turned
 * up: colorLabel() declared a string return type and handed back a
 * TranslatableMarkup, fataling on the first failing palette. The analyzer
 * tests all passed throughout, because none of them went through the form.
 *
 * @see \Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm
 */
class PaletteContrastWarningTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'geofield', 'leaflet', 'mukurtu_core', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['mukurtu_design']);
    $this->container->get('theme_installer')->install(['mukurtu_v4']);
  }

  /**
   * Runs the form's validation against a set of values.
   *
   * @return array
   *   [$warnings, $errors].
   */
  private function validate(array $values): array {
    $form_state = new FormState();
    $form_state->setValues($values);

    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm');
    $form = $this->container->get('form_builder')->getForm($form_object);
    $form_object->validateForm($form, $form_state);

    return [
      $this->container->get('messenger')->deleteByType('warning'),
      $form_state->getErrors(),
    ];
  }

  /**
   * A failing custom palette warns, names the colours, and still saves.
   */
  public function testFailingCustomPaletteWarnsWithoutBlocking(): void {
    [$warnings, $errors] = $this->validate([
      'palette' => 'custom',
      'colors' => [
        'brand_primary' => '#138aab',
        'brand_primary_dark' => '#107996',
        'brand_primary_accent' => '#159ec4',
        'brand_secondary' => '#e6ab49',
        'brand_secondary_dark' => '#9d6915',
        'brand_secondary_accent' => '#f1b85a',
      ],
    ]);

    $this->assertNotEmpty($warnings, 'A failing palette produces warnings.');
    $this->assertSame([], $errors, 'It does not block the save: ATAG B.2.2 guides, it does not refuse.');

    $text = implode("\n", array_map('strval', $warnings));
    // The author needs to know which colour to change, so the warning uses
    // the form's own label rather than the CSS custom property name.
    $this->assertStringContainsString('Brand Secondary', $text);
    $this->assertStringContainsString(':1', $text, 'The measured ratio is quoted.');
  }

  /**
   * A high-contrast custom palette says nothing at all.
   */
  public function testCleanCustomPaletteIsSilent(): void {
    [$warnings, $errors] = $this->validate([
      'palette' => 'custom',
      'colors' => [
        'brand_primary' => '#000000',
        'brand_primary_dark' => '#000000',
        'brand_primary_accent' => '#000000',
        'brand_secondary' => '#ffffff',
        'brand_secondary_dark' => '#000000',
        'brand_secondary_accent' => '#ffffff',
      ],
    ]);

    $this->assertSame([], $warnings);
    $this->assertSame([], $errors);
  }

  /**
   * The built-in palettes are not re-checked on every save.
   *
   * Their colours are not the author's to change here, so warning about
   * them would be noise the author cannot act on.
   */
  public function testBuiltInPalettesAreNotChecked(): void {
    [$warnings, $errors] = $this->validate([
      'palette' => 'blue-gold',
      'colors' => [
        'brand_primary' => '#138aab',
        'brand_primary_dark' => '#107996',
        'brand_secondary' => '#e6ab49',
      ],
    ]);

    $this->assertSame([], $warnings);
    $this->assertSame([], $errors);
  }


  /**
   * The live readout is a sibling of the colour fieldset, not a child.
   *
   * $form['colors'] is #tree'd and submitForm() saves $values['colors']
   * wholesale, so a display-only element nested inside it would be
   * persisted into config as though it were a colour.
   */
  public function testReadoutIsNotInsideTheSavedColourTree(): void {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm');
    $form = $this->container->get('form_builder')->getForm($form_object);

    $this->assertArrayHasKey('contrast_summary', $form['colors_layout']);
    $this->assertArrayNotHasKey('contrast_summary', $form['colors_layout']['colors']);
    $this->assertSame(
      ['value' => 'custom'],
      $form['colors_layout']['contrast_summary']['#states']['visible'][':input[name="palette"]'],
      'The readout only shows for the custom palette.',
    );
  }

  /**
   * Changing a colour refreshes the readout and announces the result.
   *
   * The readout is replaced wholesale, so an aria-live region inside it is
   * destroyed and recreated by the very command meant to announce it and
   * never fires. A separate announcement is what makes the change
   * perceivable to a screen reader user - which matters more than usual
   * for a feature whose entire purpose is accessibility.
   */
  public function testColourChangeRefreshesAndAnnounces(): void {
    $response = $this->ajaxFor([
      'brand_primary' => '#138aab',
      'brand_primary_dark' => '#107996',
      'brand_secondary' => '#e6ab49',
    ]);

    $commands = array_column($response->getCommands(), 'command');
    $this->assertContains('insert', $commands, 'The readout is replaced.');
    $this->assertContains('announce', $commands, 'And the change is announced.');
    $this->assertStringContainsString('need', $this->announcementFrom($response));
  }

  /**
   * A clean palette announces the all-clear rather than saying nothing.
   */
  public function testCleanPaletteAnnouncesTheAllClear(): void {
    $response = $this->ajaxFor([
      'brand_primary' => '#000000',
      'brand_primary_dark' => '#000000',
      'brand_primary_accent' => '#000000',
      'brand_secondary' => '#ffffff',
      'brand_secondary_dark' => '#000000',
      'brand_secondary_accent' => '#ffffff',
    ]);

    $this->assertStringContainsString('meet WCAG AA', $this->announcementFrom($response));
  }

  /**
   * Colours the author does not edit are named by value, not by token.
   *
   * "#fff on Brand Primary Accent" tells an author something. The raw
   * "--light-text-color on Brand Primary Accent" does not.
   */
  public function testUneditableColoursAreNamedByValue(): void {
    [$warnings] = $this->validate([
      'palette' => 'custom',
      'colors' => [
        'brand_primary_accent' => '#f1b85a',
      ],
    ]);

    $text = implode("\n", array_map('strval', $warnings));
    $this->assertNotEmpty($warnings);
    $this->assertStringNotContainsString('--light-text-color', $text);
  }

  /**
   * Runs the AJAX callback for a set of colours.
   */
  private function ajaxFor(array $colors) {
    $form_object = $this->container->get('class_resolver')
      ->getInstanceFromDefinition('Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm');
    $form = $this->container->get('form_builder')->getForm($form_object);

    $form_state = new FormState();
    $form_state->setValues(['colors' => $colors]);

    return $form_object->contrastSummaryAjaxCallback($form, $form_state);
  }

  /**
   * The text of the response's announcement.
   */
  private function announcementFrom($response): string {
    foreach ($response->getCommands() as $command) {
      if (($command['command'] ?? NULL) === 'announce') {
        return (string) $command['text'];
      }
    }
    return '';
  }

}
