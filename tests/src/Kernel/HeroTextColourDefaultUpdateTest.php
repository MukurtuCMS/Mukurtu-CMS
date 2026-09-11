<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40030().
 *
 * The full-background hero block has an optional image and shipped a default
 * text colour of light, so a block created with only a title rendered white
 * text on the white page background. The hook flips that default, but must
 * not overrule a site that chose its own.
 */
#[Group('mukurtu')]
class HeroTextColourDefaultUpdateTest extends KernelTestBase {

  protected const FIELD = 'field.field.block_content.full_image_with_description.field_text_color';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'field', 'block_content', 'options', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Writes the shipped field config with the given default value.
   */
  protected function writeField(?array $default_value): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $data = (new FileStorage($profile_path . '/config/install'))->read(self::FIELD);
    $this->assertIsArray($data);

    $data['default_value'] = $default_value;
    \Drupal::configFactory()->getEditable(self::FIELD)->setData($data)->save();
  }

  /**
   * Returns the stored default value.
   */
  protected function defaultValue() {
    return \Drupal::config(self::FIELD)->get('default_value');
  }

  /**
   * The shipped light default is flipped to dark.
   */
  public function testFlipsTheShippedDefault(): void {
    $this->writeField([['value' => 'light']]);

    $message = mukurtu_update_40030();

    $this->assertSame([['value' => 'dark']], $this->defaultValue());
    $this->assertStringContainsString('dark text', $message);
  }

  /**
   * A site that picked its own default keeps it.
   */
  public function testLeavesCustomisedDefaultAlone(): void {
    $this->writeField([]);

    mukurtu_update_40030();

    $this->assertSame([], $this->defaultValue());
  }

  /**
   * Running twice is a no-op the second time.
   */
  public function testIsIdempotent(): void {
    $this->writeField([['value' => 'light']]);

    mukurtu_update_40030();
    $message = mukurtu_update_40030();

    $this->assertSame([['value' => 'dark']], $this->defaultValue());
    $this->assertStringContainsString('already been changed', $message);
  }

  /**
   * A site without the bundle installed is left alone.
   */
  public function testSkipsWhenTheFieldIsAbsent(): void {
    $message = mukurtu_update_40030();

    $this->assertNull($this->defaultValue());
    $this->assertStringContainsString('not installed', $message);
  }

  /**
   * The shipped config and the hook must agree on the new default.
   */
  public function testShippedConfigMatchesTheHook(): void {
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $shipped = (new FileStorage($profile_path . '/config/install'))->read(self::FIELD);

    $this->assertSame([['value' => 'dark']], $shipped['default_value'], 'A fresh install must get the same default the update hook writes.');
  }

}
