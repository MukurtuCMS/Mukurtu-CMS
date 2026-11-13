<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40228(), which hides article Body/Image labels.
 *
 * @see mukurtu_core_update_40228()
 */
#[Group('mukurtu_core')]
class ArticleLabelsUpdateTest extends KernelTestBase {

  /**
   * The display config is written raw, without the fields it references.
   *
   * @var bool
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The article display config name.
   */
  protected const NAME = 'core.entity_view_display.node.article.default';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required directly rather than via loadInclude(); see
    // CategoryAdminLinksCascadeUpdateTest::setUp() for why.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_core.install';
  }

  /**
   * Writes the article display with the given labels.
   */
  protected function writeDisplay(string $body_label, string $image_label): void {
    $this->config(self::NAME)->setData([
      'id' => 'node.article.default',
      'targetEntityType' => 'node',
      'bundle' => 'article',
      'mode' => 'default',
      'content' => [
        'body' => ['type' => 'text_default', 'label' => $body_label],
        'field_article_image' => ['type' => 'image', 'label' => $image_label],
        'field_article_category' => ['type' => 'entity_reference_label', 'label' => 'above'],
      ],
    ])->save();
  }

  /**
   * The shipped "above" labels are hidden; the term labels are untouched.
   */
  public function testShippedLabelsAreHidden(): void {
    $this->writeDisplay('above', 'above');

    mukurtu_core_update_40228();

    $config = $this->config(self::NAME);
    $this->assertSame('hidden', $config->get('content.body.label'));
    $this->assertSame('hidden', $config->get('content.field_article_image.label'));
    $this->assertSame('above', $config->get('content.field_article_category.label'));
  }

  /**
   * A label a site changed from the shipped setting is kept.
   */
  public function testCustomizedLabelIsKept(): void {
    $this->writeDisplay('inline', 'above');

    mukurtu_core_update_40228();

    $config = $this->config(self::NAME);
    $this->assertSame('inline', $config->get('content.body.label'));
    $this->assertSame('hidden', $config->get('content.field_article_image.label'));
  }

  /**
   * A site without the article display is left alone.
   */
  public function testMissingDisplayIsNoOp(): void {
    mukurtu_core_update_40228();

    $this->assertTrue($this->config(self::NAME)->isNew());
  }

}
