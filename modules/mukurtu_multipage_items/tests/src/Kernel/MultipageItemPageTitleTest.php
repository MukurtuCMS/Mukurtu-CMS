<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\Core\Render\Element;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController;

/**
 * Tests the current page heading on multipage items (#598).
 *
 * @group mukurtu_multipage_items
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_multipage_items')]
class MultipageItemPageTitleTest extends MultipageItemTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block_content',
    'content_moderation',
    'field',
    'field_group',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'media',
    'node',
    'node_access_test',
    'og',
    'options',
    'path',
    'path_alias',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_collection',
    'mukurtu_community_records',
    'mukurtu_core',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_multipage_items',
    'mukurtu_protocol',
  ];

  /**
   * The controller under test.
   */
  protected MultipageItemPageController $controller;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Let 'page' nodes have community records.
    FieldStorageConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'node'],
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_mukurtu_original_record',
      'entity_type' => 'node',
      'bundle' => 'page',
      'label' => 'Original Record',
    ])->save();

    $this->controller = MultipageItemPageController::create(\Drupal::getContainer());
  }

  /**
   * Saves a multipage item made of the given pages.
   */
  protected function createMultipageItem(array $pages): void {
    $mpi = $this->buildMultipageItem('Notebook');
    foreach ($pages as $page) {
      $mpi->addPage($page);
    }
    $mpi->save();
  }

  /**
   * Returns the HTML the AJAX page switch puts on the page.
   */
  protected function ajaxPageHtml($page): string {
    // The response renders its commands as they are added.
    $commands = $this->controller->getSelectedPageAjax($page)->getCommands();
    $this->assertCount(1, $commands);
    $this->assertSame('replaceWith', $commands[0]['method']);
    $this->assertSame('#mukurtu-multipage-item-selected-page', $commands[0]['selector']);
    return (string) $commands[0]['data'];
  }

  /**
   * A page without community records gets a heading above it.
   */
  public function testPageHeading(): void {
    $first = $this->buildItem('First page');
    $first->save();
    $second = $this->buildItem('Second <b>page</b>');
    $second->save();
    $this->createMultipageItem([$first, $second]);

    $build = $this->controller->view($second)['#current_page'];
    $this->assertSame(['title', 'page'], Element::children($build));
    $heading = $build['title']['heading'];
    $this->assertSame('html_tag', $heading['#type']);
    $this->assertSame('h2', $heading['#tag']);
    // The title is text, so markup in it is escaped rather than rendered.
    $this->assertSame('Second &lt;b&gt;page&lt;/b&gt;', $heading['#value']);
    $this->assertSame((int) $second->id(), (int) $build['page']['#node']->id());

    $this->assertMatchesRegularExpression('#^<div id="mukurtu-multipage-item-selected-page">\s*<div class="multipage-item__page-title-wrapper">\s*<h2 class="multipage-item__page-title">First page</h2>#', $this->ajaxPageHtml($first));
  }

  /**
   * A page with community records shows each record's title in its tab.
   */
  public function testCommunityRecordHeadings(): void {
    $page = $this->buildItem('Page two');
    $page->save();
    $record = $this->buildItem('Page two, South record');
    $record->set('field_mukurtu_original_record', $page->id());
    $record->save();
    $this->createMultipageItem([$page]);

    $build = $this->controller->view($page)['#current_page'];
    $this->assertSame(['page'], Element::children($build));
    $this->assertTrue($build['page']['template']['#show_titles']);

    $html = $this->ajaxPageHtml($page);
    $this->assertStringNotContainsString('multipage-item__page-title', $html);
    preg_match_all('#<h2 class="community-record__title">(.*?)</h2>#', $html, $matches);
    $this->assertSame(['Page two', 'Page two, South record'], $matches[1]);

    // Only the record being viewed starts open, so on narrow screens, where
    // the tabs stay an accordion, it is the one showing (#2557).
    preg_match_all('#<details[^>]* id="record-(\d+)"[^>]*>#', $html, $details);
    $open = array_filter(array_combine($details[1], $details[0]), fn($tag) => preg_match('#\sopen[\s>]#', $tag));
    $this->assertSame([(string) $page->id()], array_map('strval', array_keys($open)));
  }

}
