<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40031().
 *
 * The hook adds field_hero_background to an existing site. Its one subtlety is
 * that config/install states allowed_values as [{value, label}] while
 * FieldStorageConfig stores the older value-keyed map, and saving an entity
 * casts against the stored shape. Installing config never hits that, because
 * the installer writes trusted data and skips the cast - which is exactly why
 * a fresh install passed while the update hook fataled.
 */
#[Group('mukurtu')]
class HeroBackgroundFieldUpdateTest extends KernelTestBase {

  protected const FIELD_ID = 'block_content.full_image_with_description.field_hero_background';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'block_content', 'options', 'text', 'filter'];

  /**
   * {@inheritdoc}
   *
   * The testAddsTheFieldToBothDisplays case writes the two entity displays
   * without the field instances they normally reference, which strict schema
   * checking rejects. Installing the full bundle to satisfy it would mean
   * asserting against a fixture rather than against the hook.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('block_content');
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';

    // Only the bundle is needed; creating the field is the behaviour on test.
    \Drupal::entityTypeManager()->getStorage('block_content_type')
      ->create($this->shipped('block_content.type.full_image_with_description'))
      ->save();
  }

  /**
   * Reads a config file as the profile ships it.
   */
  protected function shipped(string $name): array {
    $path = \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/config/install';

    return (new FileStorage($path))->read($name);
  }

  /**
   * Returns the field config entity, or NULL.
   */
  protected function field() {
    return \Drupal::entityTypeManager()->getStorage('field_config')->load(self::FIELD_ID);
  }

  /**
   * A site upgrading from before this feature gets the field.
   */
  public function testAddsTheField(): void {
    $this->assertNull($this->field(), 'The field is absent to begin with.');

    mukurtu_update_40031();

    $field = $this->field();
    $this->assertNotNull($field, 'The hook created the field.');
    $this->assertSame('Background', $field->label());
    $this->assertSame([['value' => 'none']], $field->getDefaultValueLiteral(), 'Existing blocks keep their appearance.');
  }

  /**
   * The three presets survive the shape conversion intact.
   *
   * This is the case that fataled before: passing the shipped [{value, label}]
   * form straight into FieldStorageConfig threw "The configuration property
   * settings.allowed_values.0.label.0 doesn't exist".
   */
  public function testConvertsAllowedValuesWithoutLosingAny(): void {
    mukurtu_update_40031();

    $storage = \Drupal::entityTypeManager()->getStorage('field_storage_config')
      ->load('block_content.field_hero_background');

    $this->assertSame(
      ['none' => 'None', 'brand' => 'Brand color', 'soft' => 'Soft brand color'],
      $storage->getSetting('allowed_values')
    );
  }

  /**
   * Running twice reports no work and does not recreate the field.
   */
  public function testIsIdempotent(): void {
    mukurtu_update_40031();
    $uuid = $this->field()->uuid();

    $message = mukurtu_update_40031();

    $this->assertSame($uuid, $this->field()->uuid());
    $this->assertStringContainsString('already present', $message);
  }

  /**
   * A site without the hero bundle is left alone.
   */
  public function testSkipsWhenTheBundleIsAbsent(): void {
    \Drupal::entityTypeManager()->getStorage('block_content_type')
      ->load('full_image_with_description')->delete();

    $message = mukurtu_update_40031();

    $this->assertNull($this->field());
    $this->assertStringContainsString('not installed', $message);
  }

  /**
   * The hook puts the field on both displays.
   *
   * The template reads the rendered field, so missing it off the view display
   * would silently give every upgraded site the same ground.
   */
  public function testAddsTheFieldToBothDisplays(): void {
    foreach (['core.entity_form_display', 'core.entity_view_display'] as $prefix) {
      $id = "$prefix.block_content.full_image_with_description.default";
      \Drupal::configFactory()->getEditable($id)->setData($this->shipped($id) + ['content' => []])->save();
      \Drupal::configFactory()->getEditable($id)->clear('content.field_hero_background')->save();
    }

    mukurtu_update_40031();

    foreach (['core.entity_form_display', 'core.entity_view_display'] as $prefix) {
      $content = \Drupal::config("$prefix.block_content.full_image_with_description.default")->get('content');
      $this->assertArrayHasKey('field_hero_background', $content, "$prefix carries the field.");
    }
  }

}
