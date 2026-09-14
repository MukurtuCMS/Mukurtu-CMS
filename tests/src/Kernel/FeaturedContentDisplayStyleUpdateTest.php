<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40032(), which adds Display style to Featured Content.
 *
 * The hook has to leave every existing block rendering exactly as it does now:
 * the carousel is opt-in, so a site that updates and changes nothing should see
 * no difference at all.
 *
 * @see mukurtu_update_40032()
 */
#[Group('mukurtu')]
class FeaturedContentDisplayStyleUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'block_content', 'field', 'options', 'text'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('block_content');

    BlockContentType::create([
      'id' => 'featured_content',
      'label' => 'Featured Content',
    ])->save();

    // Required directly rather than via loadInclude(): the profile is not an
    // enabled module here, so loadInclude() would silently do nothing.
    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Creates a Featured Content block, as a site would have before the update.
   */
  private function makeBlock(string $info): BlockContent {
    $block = BlockContent::create(['type' => 'featured_content', 'info' => $info]);
    $block->save();

    return $block;
  }

  /**
   * The hook creates the field and leaves existing blocks as grids.
   */
  public function testTheFieldIsAddedAndExistingBlocksStayGrids(): void {
    $one = $this->makeBlock('Homepage features');
    $two = $this->makeBlock('Sidebar features');

    $this->assertNull(FieldStorageConfig::loadByName('block_content', 'field_display_style'));

    mukurtu_update_40032();

    $storage = FieldStorageConfig::loadByName('block_content', 'field_display_style');
    $this->assertNotNull($storage);
    // FieldStorageConfig keeps allowed_values as a value-keyed map. The
    // config/install copy uses a list of value/label pairs for the same data,
    // and passing that shape to an entity save throws.
    $this->assertSame(
      ['grid' => 'Grid', 'carousel' => 'Carousel'],
      $storage->getSetting('allowed_values')
    );

    $field = FieldConfig::loadByName('block_content', 'featured_content', 'field_display_style');
    $this->assertNotNull($field);
    $this->assertTrue($field->isRequired());

    // The point of the whole hook: nothing that exists today changes.
    foreach ([$one, $two] as $block) {
      $reloaded = BlockContent::load($block->id());
      $this->assertSame('grid', $reloaded->get('field_display_style')->value);
    }
  }

  /**
   * Running the hook twice changes nothing the second time.
   */
  public function testTheHookIsIdempotent(): void {
    $block = $this->makeBlock('Homepage features');

    mukurtu_update_40032();
    BlockContent::load($block->id())->set('field_display_style', 'carousel')->save();

    $second = mukurtu_update_40032();

    // A deliberate choice must survive a re-run.
    $this->assertSame('carousel', BlockContent::load($block->id())->get('field_display_style')->value);
    $this->assertStringContainsString('already present', $second);
  }

  /**
   * A block added between the two runs is still backfilled.
   */
  public function testABlockAddedLaterIsBackfilled(): void {
    mukurtu_update_40032();

    // Saved without the field set, the way a migration or an import can.
    $late = BlockContent::create(['type' => 'featured_content', 'info' => 'Late arrival']);
    $late->set('field_display_style', NULL);
    $late->save();
    $this->assertNull(BlockContent::load($late->id())->get('field_display_style')->value);

    mukurtu_update_40032();

    $this->assertSame('grid', BlockContent::load($late->id())->get('field_display_style')->value);
  }

}
