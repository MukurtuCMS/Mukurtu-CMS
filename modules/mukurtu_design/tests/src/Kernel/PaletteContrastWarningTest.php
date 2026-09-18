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

}
