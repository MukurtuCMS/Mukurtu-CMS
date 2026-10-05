<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Entity\Entity\EntityViewMode;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\image\Entity\ImageStyle;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40207(), which sharpens full-width hero images.
 *
 * @see mukurtu_core_update_40207()
 */
#[Group('mukurtu_core')]
class HeroFullWidthImageUpdateTest extends KernelTestBase {

  use MediaTypeCreationTrait;

  /**
   * {@inheritdoc}
   *
   * The mukurtu_content_warnings module is not enabled (its dependency chain
   * is far heavier than this hook needs), so its settings have no schema.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'file',
    'image',
    'media',
    'block',
    'block_content',
    'text',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('media');
    $this->installEntitySchema('block_content');
    $this->installConfig(['system', 'field', 'image', 'media']);

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';

    $this->createMediaType('image', ['id' => 'image']);
    EntityViewMode::create([
      'id' => 'media.image_with_description',
      'label' => 'Image with Description',
      'targetEntityType' => 'media',
    ])->save();

    BlockContentType::create(['id' => 'full_image_with_description', 'label' => 'Full'])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'block_content',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'media'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_image',
      'entity_type' => 'block_content',
      'bundle' => 'full_image_with_description',
    ])->save();
  }

  /**
   * Creates the full-width block display as it shipped before the fix.
   */
  private function makeBlockDisplay(string $view_mode = 'image_with_description'): void {
    EntityViewDisplay::create([
      'targetEntityType' => 'block_content',
      'bundle' => 'full_image_with_description',
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent('field_image', [
      'type' => 'entity_reference_entity_view',
      'label' => 'hidden',
      'settings' => ['view_mode' => $view_mode, 'link' => FALSE],
    ])->save();
  }

  /**
   * Reads the block display's field_image view mode back from storage.
   */
  private function blockViewMode(): ?string {
    $display = EntityViewDisplay::load('block_content.full_image_with_description.default');
    return $display->getComponent('field_image')['settings']['view_mode'] ?? NULL;
  }

  /**
   * The hero gets its own 1920px view mode, style and display.
   */
  public function testSwitchesHeroToFullWidthStyle(): void {
    $this->makeBlockDisplay();
    $this->assertNull(ImageStyle::load('hero_full_width'), 'Precondition: style already exists.');

    mukurtu_core_update_40207();

    $style = ImageStyle::load('hero_full_width');
    $this->assertNotNull($style);
    $effect = $style->getEffects()->getConfiguration();
    $effect = reset($effect);
    $this->assertSame('image_scale', $effect['id']);
    $this->assertSame(1920, $effect['data']['width']);
    $this->assertFalse($effect['data']['upscale']);

    $this->assertNotNull(EntityViewMode::load('media.hero_full_width'));

    $media_display = EntityViewDisplay::load('media.image.hero_full_width');
    $this->assertNotNull($media_display);
    $this->assertSame('hero_full_width', $media_display->getComponent('field_media_image')['settings']['image_style']);

    $this->assertSame('hero_full_width', $this->blockViewMode());
  }

  /**
   * A site that picked its own view mode for the hero keeps it.
   */
  public function testLeavesCustomViewModeAlone(): void {
    $this->makeBlockDisplay('full');

    mukurtu_core_update_40207();

    $this->assertSame('full', $this->blockViewMode());
  }

  /**
   * Content warning view mode selections carry over to the new view mode.
   *
   * @param array|null $before
   *   The saved warning_view_modes before the update.
   * @param array|null $expected
   *   The saved warning_view_modes after the update.
   */
  #[DataProvider('warningModesProvider')]
  public function testCarriesOverContentWarningViewModes(?array $before, ?array $expected): void {
    $this->makeBlockDisplay();
    $config = $this->config('mukurtu_content_warnings.settings');
    $before === NULL ? $config->clear('warning_view_modes') : $config->set('warning_view_modes', $before);
    $config->save();

    mukurtu_core_update_40207();

    $this->assertSame($expected, $this->config('mukurtu_content_warnings.settings')->get('warning_view_modes'));
  }

  /**
   * Saved warning view modes before and after the update.
   */
  public static function warningModesProvider(): \Generator {
    yield 'never configured means every mode' => [NULL, NULL];
    yield 'old mode enabled' => [
      ['browse', 'image_with_description'],
      ['browse', 'image_with_description', 'hero_full_width'],
    ];
    yield 'old mode disabled' => [['browse'], ['browse']];
    yield 'all disabled' => [[], []];
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeBlockDisplay();
    $this->config('mukurtu_content_warnings.settings')
      ->set('warning_view_modes', ['image_with_description'])
      ->save();

    mukurtu_core_update_40207();
    mukurtu_core_update_40207();

    $this->assertSame('hero_full_width', $this->blockViewMode());
    $this->assertSame(
      ['image_with_description', 'hero_full_width'],
      $this->config('mukurtu_content_warnings.settings')->get('warning_view_modes')
    );
  }

  /**
   * A site without the full-width block type does not error.
   */
  public function testMissingBlockDisplayDoesNotError(): void {
    $this->assertNull(mukurtu_core_update_40207());
    $this->assertNotNull(ImageStyle::load('hero_full_width'));
  }

}
