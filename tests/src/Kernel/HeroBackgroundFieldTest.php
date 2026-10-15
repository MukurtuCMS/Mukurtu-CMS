<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the shipped definition of the hero Background field.
 *
 * The full-background hero's image is optional, so leaving it empty is how a
 * site builds a text-only homepage title (#1035). field_text_color cannot
 * serve that case - "Light" is white text on the white page - so
 * field_hero_background picks a ground and the text colour follows from it.
 *
 * This asserts the shipped config; HeroBackgroundFieldUpdateTest covers adding
 * the field to an existing site.
 */
#[Group('mukurtu')]
class HeroBackgroundFieldTest extends KernelTestBase {

  protected const STORAGE = 'field.storage.block_content.field_hero_background';
  protected const FIELD = 'field.field.block_content.full_image_with_description.field_hero_background';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Reads a config file as the profile ships it.
   */
  protected function shipped(string $name): array {
    $path = \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/config/install';
    $data = (new FileStorage($path))->read($name);
    $this->assertIsArray($data, "$name ships in config/install.");

    return $data;
  }

  /**
   * The three presets, and only those three, are offered.
   *
   * Each pairs a ground with a text colour in _image-w-description.scss, so
   * adding a value here without adding the matching rule would render a hero
   * with no styling at all.
   */
  public function testOffersExactlyThreePresets(): void {
    $values = $this->shipped(self::STORAGE)['settings']['allowed_values'];

    $this->assertSame(
      ['none' => 'None', 'brand' => 'Brand color', 'soft' => 'Soft brand color'],
      array_column($values, 'label', 'value')
    );
  }

  /**
   * The field defaults to None, so existing blocks are unaffected.
   */
  public function testDefaultsToNone(): void {
    $field = $this->shipped(self::FIELD);

    $this->assertSame([['value' => 'none']], $field['default_value']);
    $this->assertTrue($field['required']);
  }

  /**
   * The field is attached to the hero bundle and declares its dependencies.
   */
  public function testIsAttachedToTheHeroBundle(): void {
    $field = $this->shipped(self::FIELD);

    $this->assertSame('full_image_with_description', $field['bundle']);
    $this->assertSame('block_content', $field['entity_type']);
    $this->assertContains(self::STORAGE, $field['dependencies']['config']);
    $this->assertContains('block_content.type.full_image_with_description', $field['dependencies']['config']);
  }

  /**
   * A fresh install must both offer the field and render it.
   *
   * The template reads the rendered field, so leaving it off the view display
   * would silently give every hero the same ground.
   */
  public function testIsOnBothDisplays(): void {
    $form = $this->shipped('core.entity_form_display.block_content.full_image_with_description.default');
    $view = $this->shipped('core.entity_view_display.block_content.full_image_with_description.default');

    $this->assertArrayHasKey('field_hero_background', $form['content'], 'Authors can set it.');
    $this->assertArrayHasKey('field_hero_background', $view['content'], 'The template can read it.');
    $this->assertContains(self::FIELD, $form['dependencies']['config']);
    $this->assertContains(self::FIELD, $view['dependencies']['config']);
  }

  /**
   * Every preset has a matching style rule in the compiled CSS.
   *
   * Guards the seam between the field's allowed values and the theme: the two
   * are edited in different files and nothing else connects them.
   */
  public function testEveryPresetHasStyleRule(): void {
    $css = file_get_contents(\Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/css/style.css');

    foreach (array_column($this->shipped(self::STORAGE)['settings']['allowed_values'], 'value') as $value) {
      $this->assertStringContainsString(".hero-ground-$value", $css, "The $value preset has a style rule.");
    }
  }

}
