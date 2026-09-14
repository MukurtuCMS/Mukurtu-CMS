<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\user\RoleInterface;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the carousel display style on Featured Content blocks.
 *
 * The block renders as a grid unless a site deliberately switches it over, so
 * most of what matters here is what does NOT happen: the default path must not
 * render any carousel markup or attach any carousel JavaScript.
 *
 * @see mukurtu_v4_preprocess_block__block_content__type__featured_content()
 */
#[Group('mukurtu')]
class FeaturedContentCarouselTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system', 'user', 'node', 'block_content', 'field', 'options', 'text', 'filter',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('block_content');
    $this->installConfig(['filter', 'node', 'user']);

    // The preprocess filters on access('view'), so without this every node is
    // dropped and a carousel silently falls back to a grid.
    user_role_grant_permissions(RoleInterface::ANONYMOUS_ID, ['access content']);

    NodeType::create(['type' => 'page', 'name' => 'Basic page'])->save();
    BlockContentType::create(['id' => 'featured_content', 'label' => 'Featured Content'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_display_style',
      'entity_type' => 'block_content',
      'type' => 'list_string',
      'settings' => ['allowed_values' => ['grid' => 'Grid', 'carousel' => 'Carousel']],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_display_style',
      'entity_type' => 'block_content',
      'bundle' => 'featured_content',
      'label' => 'Display style',
      'default_value' => [['value' => 'grid']],
    ])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_featured_content',
      'entity_type' => 'block_content',
      'type' => 'entity_reference',
      'cardinality' => -1,
      'settings' => ['target_type' => 'node'],
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_featured_content',
      'entity_type' => 'block_content',
      'bundle' => 'featured_content',
      'label' => 'Featured content',
    ])->save();

    require_once \Drupal::root() . '/' . \Drupal::service('extension.list.theme')->getPath('mukurtu_v4') . '/mukurtu_v4.theme';
  }

  /**
   * Builds a block and returns what the preprocess made of it.
   */
  private function preprocess(string $style, int $item_count): array {
    $nodes = [];
    for ($i = 0; $i < $item_count; $i++) {
      $node = Node::create(['type' => 'page', 'title' => 'Item ' . $i, 'status' => 1]);
      $node->save();
      $nodes[] = ['target_id' => $node->id()];
    }

    $block = BlockContent::create([
      'type' => 'featured_content',
      'info' => 'Featured',
      'field_display_style' => $style,
      'field_featured_content' => $nodes,
    ]);
    $block->save();

    $variables = ['content' => ['#block_content' => $block]];
    mukurtu_v4_preprocess_block__block_content__type__featured_content($variables);

    return $variables;
  }

  /**
   * Grid is the default and renders nothing carousel-shaped.
   */
  public function testGridIsUntouched(): void {
    $variables = $this->preprocess('grid', 4);

    $this->assertSame('grid', $variables['display_style']);
    $this->assertArrayNotHasKey('featured_items', $variables);
    // The library is the expensive part; a grid must not pay for it.
    $this->assertArrayNotHasKey('#attached', $variables);
  }

  /**
   * Carousel renders each item separately and attaches the library.
   */
  public function testCarouselBuildsSlidesAndAttachesTheLibrary(): void {
    $variables = $this->preprocess('carousel', 4);

    $this->assertSame('carousel', $variables['display_style']);
    $this->assertCount(4, $variables['featured_items']);
    $this->assertArrayHasKey('#attached', $variables, 'The carousel needs its JavaScript.');
    $this->assertContains(
      'mukurtu_v4/featured-content-carousel',
      $variables['#attached']['library']
    );
  }

  /**
   * A carousel of one falls back to the grid.
   *
   * Controls that can only move between one item are noise, and a pause button
   * for something that cannot move is worse than none.
   */
  public function testASingleItemFallsBackToGrid(): void {
    $variables = $this->preprocess('carousel', 1);

    $this->assertSame('grid', $variables['display_style']);
    $this->assertArrayNotHasKey('featured_items', $variables);
  }

  /**
   * A block with no items at all does not become a carousel.
   */
  public function testAnEmptyBlockFallsBackToGrid(): void {
    $variables = $this->preprocess('carousel', 0);

    $this->assertSame('grid', $variables['display_style']);
  }

  /**
   * A block that predates the field still renders.
   *
   * The update hook backfills, but config import order and staged content can
   * both produce a block the field has not reached yet.
   */
  public function testAMissingValueIsTreatedAsGrid(): void {
    $block = BlockContent::create(['type' => 'featured_content', 'info' => 'Old']);
    $block->set('field_display_style', NULL);
    $block->save();

    $variables = ['content' => ['#block_content' => $block]];
    mukurtu_v4_preprocess_block__block_content__type__featured_content($variables);

    $this->assertSame('grid', $variables['display_style']);
  }

  /**
   * Items the visitor cannot see are left out.
   *
   * The carousel renders each referenced entity itself rather than going
   * through the field formatter, so it has to do the access check that the
   * formatter would otherwise have done.
   */
  public function testInaccessibleItemsAreExcluded(): void {
    $visible = [];
    foreach ([TRUE, TRUE, FALSE] as $published) {
      $node = Node::create([
        'type' => 'page',
        'title' => $published ? 'Published' : 'Unpublished',
        'status' => $published,
      ]);
      $node->save();
      $visible[] = ['target_id' => $node->id()];
    }

    $block = BlockContent::create([
      'type' => 'featured_content',
      'info' => 'Featured',
      'field_display_style' => 'carousel',
      'field_featured_content' => $visible,
    ]);
    $block->save();

    $variables = ['content' => ['#block_content' => $block]];
    mukurtu_v4_preprocess_block__block_content__type__featured_content($variables);

    $this->assertCount(2, $variables['featured_items'], 'The unpublished node is not a slide.');
  }

  /**
   * The shipped config matches what the update hook builds.
   *
   * An update hook only ever runs on an existing site. Without the same change
   * in config/install, a fresh install would not get the field at all.
   */
  public function testTheShippedConfigDefaultsToGrid(): void {
    $path = \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/config/install/';

    $storage = Yaml::parseFile(\Drupal::root() . '/' . $path . 'field.storage.block_content.field_display_style.yml');
    $values = array_column($storage['settings']['allowed_values'], 'value');
    $this->assertSame(['grid', 'carousel'], $values);

    $field = Yaml::parseFile(\Drupal::root() . '/' . $path . 'field.field.block_content.featured_content.field_display_style.yml');
    $this->assertSame([['value' => 'grid']], $field['default_value']);
    $this->assertTrue($field['required']);

    // The value drives the wrapper markup, so it must not also render as a
    // field inside the block.
    $view = Yaml::parseFile(\Drupal::root() . '/' . $path . 'core.entity_view_display.block_content.featured_content.default.yml');
    $this->assertArrayHasKey('field_display_style', $view['hidden']);
    $this->assertArrayNotHasKey('field_display_style', $view['content']);

    // ...but an author has to be able to choose it.
    $form = Yaml::parseFile(\Drupal::root() . '/' . $path . 'core.entity_form_display.block_content.featured_content.default.yml');
    $this->assertArrayHasKey('field_display_style', $form['content']);
  }

}
