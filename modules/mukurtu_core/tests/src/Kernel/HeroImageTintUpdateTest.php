<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40208(), which makes the hero tint optional.
 *
 * @see mukurtu_core_update_40208()
 */
#[Group('mukurtu_core')]
class HeroImageTintUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
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
    $this->installEntitySchema('block_content');
    $this->installConfig(['system', 'field']);

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * Creates the full-width block type and its displays, minus the new field.
   */
  private function makeBlockType(): void {
    BlockContentType::create(['id' => 'full_image_with_description', 'label' => 'Full'])->save();
    EntityFormDisplay::create([
      'targetEntityType' => 'block_content',
      'bundle' => 'full_image_with_description',
      'mode' => 'default',
      'status' => TRUE,
    ])->save();
    EntityViewDisplay::create([
      'targetEntityType' => 'block_content',
      'bundle' => 'full_image_with_description',
      'mode' => 'default',
      'status' => TRUE,
    ])->save();
  }

  /**
   * Runs the update to completion, the way update.php drives a batch.
   */
  private function runUpdate(): ?string {
    $sandbox = [];
    do {
      $message = mukurtu_core_update_40208($sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1);
    return $message;
  }

  /**
   * The field is added, shown on the form and hidden from the rendered block.
   */
  public function testAddsFieldToFormNotView(): void {
    $this->makeBlockType();

    $this->runUpdate();

    $field = FieldConfig::load('block_content.full_image_with_description.field_image_tint');
    $this->assertNotNull($field);
    $this->assertSame('boolean', $field->getType());
    $this->assertEquals([['value' => 1]], $field->getDefaultValueLiteral());

    $form = EntityFormDisplay::load('block_content.full_image_with_description.default');
    $this->assertSame('boolean_checkbox', $form->getComponent('field_image_tint')['type'] ?? NULL);

    $view = EntityViewDisplay::load('block_content.full_image_with_description.default');
    $this->assertNull($view->getComponent('field_image_tint'));
  }

  /**
   * Existing blocks, more than one batch's worth, all get the tint turned on.
   */
  public function testTurnsTintOnForExistingBlocks(): void {
    $this->makeBlockType();
    $ids = [];
    for ($i = 0; $i < 55; $i++) {
      $block = BlockContent::create(['type' => 'full_image_with_description', 'info' => "Hero $i"]);
      $block->save();
      $ids[] = $block->id();
    }

    $this->runUpdate();

    \Drupal::entityTypeManager()->getStorage('block_content')->resetCache();
    foreach (BlockContent::loadMultiple($ids) as $block) {
      $this->assertSame('1', (string) $block->get('field_image_tint')->value, "Block {$block->label()} lost its tint.");
    }
  }

  /**
   * A block an editor already turned off stays off on a re-run.
   */
  public function testKeepsEditorChoiceOnRerun(): void {
    $this->makeBlockType();
    $this->runUpdate();
    $block = BlockContent::create([
      'type' => 'full_image_with_description',
      'info' => 'Hero',
      'field_image_tint' => FALSE,
    ]);
    $block->save();

    $this->runUpdate();

    $block = BlockContent::load($block->id());
    $this->assertSame('0', (string) $block->get('field_image_tint')->value);
  }

  /**
   * A site without the full-width block type does not error.
   */
  public function testMissingBlockTypeDoesNotError(): void {
    $this->assertNull($this->runUpdate());
    $this->assertNull(FieldConfig::load('block_content.full_image_with_description.field_image_tint'));
  }

}
