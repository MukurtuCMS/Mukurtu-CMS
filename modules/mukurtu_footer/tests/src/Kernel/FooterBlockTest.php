<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_footer\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the MukurtuFooterBlock plugin's render output.
 */
#[Group('mukurtu_footer')]
class FooterBlockTest extends KernelTestBase {
  protected static $modules = [
    'system',
    'field',
    'block',
    'block_content',
    'user',
    'text',
    'link',
    'filter',
    'options',
    'token',
    'file',
    'image',
    'entity_reference_revisions',
    'paragraphs',
    'mukurtu_footer',
  ];

  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('block_content');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('system', 'sequences');
    $this->installSchema('file', 'file_usage');
    $this->installConfig(['field', 'filter', 'user', 'mukurtu_footer']);
  }

  /**
   * Tests build() cache tags include paragraph entity tags.
   */
  public function testBuildIncludesParagraphCacheTags(): void {
    $social = Paragraph::create([
      'type' => 'footer_social_link',
      'field_footer_social_platform' => 'twitter',
      'field_footer_social_url' => [
        'uri' => 'https://x.com/mukurtucms',
        'title' => 'Mukurtu CMS',
      ],
    ]);
    $social->save();

    $footer = BlockContent::create([
      'type' => 'mukurtu_footer',
      'info' => 'Test Footer',
      'status' => TRUE,
      'field_footer_social_links' => [
        [
          'target_id' => $social->id(),
          'target_revision_id' => $social->getRevisionId(),
        ],
      ],
    ]);
    $footer->save();

    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_footer', []);
    $build = $block->build();

    $this->assertNotEmpty($build['#cache']['tags']);
    $this->assertContains('block_content:' . $footer->id(), $build['#cache']['tags']);
    $this->assertContains('paragraph:' . $social->id(), $build['#cache']['tags']);
    $this->assertContains('block_content_list', $build['#cache']['tags']);
  }

  /**
   * Tests build() returns empty array and logs a notice when no entity exists.
   */
  public function testBuildWithNoEntityReturnsEmpty(): void {
    $block = $this->container->get('plugin.manager.block')
      ->createInstance('mukurtu_footer', []);
    $build = $block->build();

    $this->assertSame([], $build);
  }
}
