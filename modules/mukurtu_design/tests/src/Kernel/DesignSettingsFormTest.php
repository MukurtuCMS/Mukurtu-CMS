<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_design\Form\MukurtuDesignSettingsForm;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the Design Settings form can actually be built.
 *
 * The background settings were added with a create() that type-hinted the wrong
 * FileUsageInterface - Drupal\Core\File\FileUsage\FileUsageInterface, which
 * does not exist; the service implements Drupal\file\FileUsage. Every test
 * exercised the PageBackground service directly, so nothing instantiated the
 * form and /admin/config/color-settings fataled on a TypeError. These tests
 * cover the wiring itself.
 */
#[Group('mukurtu_design')]
class DesignSettingsFormTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'file', 'mukurtu_design'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
    $this->installConfig(['mukurtu_design']);
  }

  /**
   * The form can be constructed through the container.
   *
   * This is the exact path the route takes, and the one that fataled.
   */
  public function testFormCanBeInstantiated(): void {
    $form = \Drupal::classResolver(MukurtuDesignSettingsForm::class);

    $this->assertInstanceOf(MukurtuDesignSettingsForm::class, $form);
  }

  /**
   * The form builds, and offers every control the background settings need.
   */
  public function testFormBuilds(): void {
    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertSame('managed_file', $build['background']['image']['#type']);
    $this->assertSame('radios', $build['background']['text_treatment']['#type']);
    $this->assertSame(
      ['light', 'dark'],
      array_keys($build['background']['text_treatment']['#options'])
    );

    // The palette controls must survive alongside the new fieldset.
    $this->assertArrayHasKey('palette', $build);
    $this->assertArrayHasKey('colors', $build['colors_layout']);
  }

  /**
   * The colour pickers collapse unless the site is on the custom palette.
   *
   * Six colour inputs are a lot of form for something most sites never touch,
   * but a site actually using the custom palette should not have to open a
   * disclosure to reach the settings it cares about.
   */
  public function testColourPickersCollapseUnlessCustom(): void {
    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);
    $this->assertSame('details', $build['colors_layout']['colors']['#type']);
    $this->assertFalse($build['colors_layout']['colors']['#open'], 'Closed on a shipped palette.');

    $this->config('mukurtu_design.settings')->set('palette', 'custom')->save();

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);
    $this->assertTrue($build['colors_layout']['colors']['#open'], 'Open when the custom palette is in use.');
  }

  /**
   * The form defaults reflect stored configuration.
   */
  public function testFormReflectsStoredSettings(): void {
    $this->config('mukurtu_design.settings')
      ->set('background.text_treatment', 'dark')
      ->save();

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertSame('dark', $build['background']['text_treatment']['#default_value']);
  }

  /**
   * Both tint checkboxes are on for a fresh install.
   */
  public function testTintDefaultsToOn(): void {
    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertSame('checkbox', $build['background']['tint']['#type']);
    $this->assertTrue($build['background']['tint']['#default_value']);
    $this->assertSame('checkbox', $build['header']['tint']['#type']);
    $this->assertTrue($build['header']['tint']['#default_value']);
  }

  /**
   * A site upgraded from before the setting existed shows the tint as on.
   *
   * There is no update hook for the key, so a missing value has to read as the
   * scrim the site already had.
   */
  public function testMissingTintReadsAsOn(): void {
    $config = $this->config('mukurtu_design.settings');
    $config->set('background', ['image' => NULL, 'text_treatment' => 'light'])
      ->set('header', ['image' => NULL, 'text_treatment' => 'light'])
      ->save();

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertTrue($build['background']['tint']['#default_value']);
    $this->assertTrue($build['header']['tint']['#default_value']);
  }

  /**
   * Saving the form stores each tint setting on its own.
   *
   * An unchecked box is NULL input. A checkbox reads any other submitted value,
   * 0 included, as checked.
   */
  public function testSubmitStoresTint(): void {
    $form_state = (new FormState())->setValues([
      'palette' => 'red-bone',
      'background' => ['text_treatment' => 'light', 'tint' => NULL],
      'header' => ['text_treatment' => 'dark', 'tint' => 1],
      'op' => 'Save configuration',
    ]);

    \Drupal::formBuilder()->submitForm(MukurtuDesignSettingsForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $config = $this->config('mukurtu_design.settings');
    $this->assertFalse($config->get('background.tint'));
    $this->assertTrue($config->get('header.tint'));

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);
    $this->assertFalse($build['background']['tint']['#default_value']);
  }

}
