<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_design\Kernel;

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
    $this->assertSame('checkbox', $build['background']['show_on_front']['#type']);
    $this->assertSame('radios', $build['background']['text_treatment']['#type']);
    $this->assertSame(
      ['light', 'dark'],
      array_keys($build['background']['text_treatment']['#options'])
    );

    // The palette controls must survive alongside the new fieldset.
    $this->assertArrayHasKey('palette', $build);
    $this->assertArrayHasKey('colors', $build);
  }

  /**
   * The form defaults reflect stored configuration.
   */
  public function testFormReflectsStoredSettings(): void {
    $this->config('mukurtu_design.settings')
      ->set('background.show_on_front', FALSE)
      ->set('background.text_treatment', 'dark')
      ->save();

    $build = \Drupal::formBuilder()->getForm(MukurtuDesignSettingsForm::class);

    $this->assertFalse((bool) $build['background']['show_on_front']['#default_value']);
    $this->assertSame('dark', $build['background']['text_treatment']['#default_value']);
  }

}
