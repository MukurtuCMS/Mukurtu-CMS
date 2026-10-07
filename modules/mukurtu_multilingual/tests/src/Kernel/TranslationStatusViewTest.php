<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multilingual\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_multilingual\TranslationStatus\TranslationStatusTracker;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\views\Views;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the shipped translation status views against real data.
 *
 * Loads the views from config/install rather than building fixtures, so
 * these tests break if the shipped YAML stops working.
 */
#[Group('mukurtu_multilingual')]
class TranslationStatusViewTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'node_access_test',
    'block_content',
    'content_moderation',
    'workflows',
    'filter',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'taxonomy',
    'options',
    'views',
    'language',
    'content_translation',
    'og',
    'mukurtu_core',
    'mukurtu_protocol',
    'mukurtu_multilingual',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    // mukurtu_protocol's node grants read OG memberships and its own maps.
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installSchema('mukurtu_protocol', ['mukurtu_protocol_map', 'mukurtu_protocol_access']);
    $this->installSchema('mukurtu_multilingual', [TranslationStatusTracker::TABLE]);
    $this->installConfig(['node', 'language']);

    ConfigurableLanguage::createFromLangcode('fr')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    $this->container->get('content_translation.manager')->setEnabled('node', 'article', TRUE);
    \Drupal::service('entity_field.manager')->clearCachedFieldDefinitions();

    $source = new FileStorage(\Drupal::service('extension.list.module')->getPath('mukurtu_multilingual') . '/config/install');
    $storage = $this->container->get('entity_type.manager')->getStorage('view');
    foreach (['content', 'media', 'taxonomy_term', 'community', 'protocol'] as $type) {
      $storage->createFromStorageRecord($source->read("views.view.mukurtu_translation_status_$type"))->save();
    }

    // The first user created is uid 1, which bypasses node access. Burn it
    // so the accounts below are subject to grants.
    $this->createUser();
  }

  /**
   * Runs the content view and returns "title:langcode:status" per row.
   */
  private function contentRows(array $input = []): array {
    $view = Views::getView('mukurtu_translation_status_content');
    $view->setDisplay('page_1');
    $view->setExposedInput($input);
    $view->execute();
    $rows = [];
    foreach ($view->result as $row) {
      $rows[] = $row->_entity->label() . ':' . $view->field['langcode_1']->getValue($row) . ':' . $view->field['translation_status']->getValue($row);
    }
    sort($rows);
    return $rows;
  }

  /**
   * Each item gets one row per target language, with its status.
   */
  public function testRowsAndFilters(): void {
    $this->setCurrentUser($this->createUser(['node test view']));
    $owner = $this->createUser();
    Node::create(['type' => 'article', 'title' => 'Dawn', 'langcode' => 'en', 'uid' => $owner->id()])->save();
    $translated = Node::create(['type' => 'article', 'title' => 'Crow', 'langcode' => 'en', 'uid' => $owner->id()]);
    $translated->save();
    $translated->addTranslation('fr', ['title' => 'Corbeau'] + $translated->toArray())->save();

    $this->assertSame([
      'Crow:es:0',
      'Crow:fr:2',
      'Dawn:es:0',
      'Dawn:fr:0',
    ], $this->contentRows());

    $this->assertSame(['Crow:fr:2'], $this->contentRows(['status' => [TranslationStatusTracker::STATUS_COMPLETE]]));
    $this->assertSame(['Crow:fr:2', 'Dawn:fr:0'], $this->contentRows(['language' => ['fr']]));
  }

  /**
   * Rows follow node access, so people only see items they can already see.
   *
   * The node_access_test module grants view access to holders of "node test
   * view" and to each node's author, through the same node grants system
   * that Mukurtu's protocols use.
   */
  public function testRowsRespectNodeAccess(): void {
    $owner = $this->createUser();
    Node::create(['type' => 'article', 'title' => 'Restricted', 'langcode' => 'en', 'uid' => $owner->id()])->save();
    $this->container->get('Drupal\node\NodeAccessRebuild')->rebuild();

    $this->setCurrentUser($this->createUser());
    $this->assertSame([], $this->contentRows());

    $this->setCurrentUser($this->createUser(['node test view']));
    $this->assertSame(['Restricted:es:0', 'Restricted:fr:0'], $this->contentRows());
  }

  /**
   * Each Translate link names its item and language, and opens the right form.
   */
  public function testTranslateLinks(): void {
    $this->container->get('router.builder')->rebuild();
    $this->setCurrentUser($this->createUser([
      'node test view',
      'bypass node access',
      'translate any entity',
      'create content translations',
      'update content translations',
    ]));
    $dawn = Node::create(['type' => 'article', 'title' => 'Dawn', 'langcode' => 'en']);
    $dawn->save();
    $dawn->addTranslation('fr', ['title' => 'Aube'] + $dawn->toArray())->save();

    $view = Views::getView('mukurtu_translation_status_content');
    $view->setDisplay('page_1');
    $view->execute();
    $links = [];
    foreach ($view->result as $index => $row) {
      $links[$view->field['langcode_1']->getValue($row)] = (string) $view->style_plugin->getField($index, 'translate_link');
    }

    $this->assertStringContainsString('<span class="visually-hidden"> Dawn into French</span>', $links['fr']);
    $this->assertStringContainsString('/node/' . $dawn->id() . '/edit', $links['fr'], 'An existing translation opens its edit form.');
    $this->assertStringContainsString('<span class="visually-hidden"> Dawn into Spanish</span>', $links['es']);
    $this->assertStringContainsString('/node/' . $dawn->id() . '/translations/add/en/es', $links['es'], 'A missing translation opens the add form.');
  }

  /**
   * Lists each view with the access query tag of its entity type.
   */
  public static function accessTagProvider(): array {
    return [
      'content' => ['mukurtu_translation_status_content', 'node_access'],
      'media' => ['mukurtu_translation_status_media', 'media_access'],
      'taxonomy terms' => ['mukurtu_translation_status_taxonomy_term', 'taxonomy_term_access'],
      'communities' => ['mukurtu_translation_status_community', 'community_access'],
      'protocols' => ['mukurtu_translation_status_protocol', 'protocol_access'],
    ];
  }

  /**
   * Every view's query gets its entity type's access tag.
   *
   * Mukurtu applies protocol access to media, communities and protocols
   * through these tags (mukurtu_protocol_query_*_access_alter()), so a view
   * built on another base table, or with SQL rewriting turned off, would
   * show rows people can't otherwise see. Views adds the tag at execute
   * time from the base table's "access query tag", so check both halves.
   */
  #[DataProvider('accessTagProvider')]
  public function testViewCarriesAccessTag(string $view_id, string $tag): void {
    $view = Views::getView($view_id);
    $view->setDisplay('page_1');
    $base_table = $view->storage->get('base_table');

    $this->assertSame($tag, Views::viewsData()->get($base_table)['table']['base']['access query tag'] ?? NULL, "$view_id's base table $base_table should apply $tag.");
    $this->assertEmpty($view->display_handler->getOption('query')['options']['disable_sql_rewrite'] ?? FALSE, "$view_id must not disable SQL rewriting.");
  }

}
