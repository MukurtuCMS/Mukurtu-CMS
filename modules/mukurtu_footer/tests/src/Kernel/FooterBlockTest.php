<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_footer\Kernel;

use Drupal\block_content\Entity\BlockContent;
use Drupal\KernelTests\KernelTestBase;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Tests the MukurtuFooterBlock plugin and mukurtu_footer_update_40001().
 *
 * @group mukurtu_footer
 */
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

  /**
   * Tests mukurtu_footer_update_40001() skips when an entity already exists.
   */
  public function testUpdateHookSkipsWhenEntityExists(): void {
    $footer = BlockContent::create([
      'type' => 'mukurtu_footer',
      'info' => 'Existing Footer',
      'status' => TRUE,
    ]);
    $footer->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40001();

    $entities = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'mukurtu_footer']);

    // Still exactly one entity — the hook did not create a duplicate.
    $this->assertCount(1, $entities);
    $this->assertEquals($footer->id(), reset($entities)->id());
  }

  /**
   * Tests mukurtu_footer_update_40001() migrates data from block settings.
   */
  public function testUpdateHookMigratesBlockSettings(): void {
    // Write a block config directly with the old plugin settings format.
    \Drupal::configFactory()
      ->getEditable('block.block.mukurtu_v4_footer_test')
      ->setData([
        'langcode' => 'en',
        'status' => TRUE,
        'id' => 'mukurtu_v4_footer_test',
        'theme' => 'stark',
        'region' => 'footer',
        'weight' => 0,
        'provider' => NULL,
        'plugin' => 'mukurtu_footer',
        'settings' => [
          'id' => 'mukurtu_footer',
          'label' => 'Mukurtu Footer',
          'social_media' => [
            'twitter' => [
              'account_1' => 'mukurtucms',
              'account_2' => '',
              'account_3' => '',
            ],
            'facebook' => [
              'account_1' => '',
              'account_2' => '',
              'account_3' => '',
            ],
            'instagram' => [
              'account_1' => '',
              'account_2' => '',
              'account_3' => '',
            ],
          ],
          'contact_email_address' => 'info@mukurtu.org',
          'email_us_text' => 'Contact us',
          'copyright_message' => '© 2024 Mukurtu CMS',
          'logo_upload' => [],
        ],
        'visibility' => [],
      ])
      ->save();

    require_once __DIR__ . '/../../../mukurtu_footer.install';
    mukurtu_footer_update_40001();

    $entities = \Drupal::entityTypeManager()
      ->getStorage('block_content')
      ->loadByProperties(['type' => 'mukurtu_footer']);

    $this->assertCount(1, $entities);
    $footer = reset($entities);

    $this->assertEquals('info@mukurtu.org', $footer->get('field_footer_contact_email')->value);
    $this->assertEquals('Contact us', $footer->get('field_footer_contact_email_label')->value);
    $this->assertEquals('© 2024 Mukurtu CMS', $footer->get('field_footer_copyright')->value);

    $social_links = $footer->get('field_footer_social_links')->referencedEntities();
    $this->assertCount(1, $social_links, 'Only the non-empty Twitter account should be migrated.');
    $this->assertEquals('twitter', $social_links[0]->get('field_footer_social_platform')->value);
    $this->assertStringContainsString('mukurtucms', $social_links[0]->get('field_footer_social_url')->uri);
  }

}
