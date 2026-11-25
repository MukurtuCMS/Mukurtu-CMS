<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\og\Entity\OgRole;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests adding words to word lists from the word's own import row.
 *
 * ImportCollectionMembershipTest covers the shared lookup, access and
 * failure handling in depth; this covers the word list specifics.
 *
 * @see \Drupal\mukurtu_import\Plugin\migrate\destination\ProtocolAwareEntityContent::resolveMembershipChanges()
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2798
 */
#[Group('mukurtu_import')]
class ImportWordListMembershipTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'blazy',
    'comment',
    'entity_browser',
    'entity_reference_revisions',
    'facets',
    'field_group',
    'layout_builder',
    'link',
    'media_library',
    'menu_ui',
    'paragraphs',
    'path',
    'path_alias',
    'search_api_glossary',
    'tagify',
    'mukurtu_browse',
    'mukurtu_collection',
    'mukurtu_dictionary',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_search',
    'mukurtu_taxonomy',
  ];

  /**
   * A word list the importing user can edit.
   */
  protected NodeInterface $wordList;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('comment');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('paragraph');
    $this->installSchema('comment', 'comment_entity_statistics');
    $this->installSchema('layout_builder', ['inline_block_usage']);

    NodeType::create(['type' => 'dictionary_word', 'name' => 'Dictionary Word'])->save();
    NodeType::create(['type' => 'word_list', 'name' => 'Word List'])->save();
    NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    ParagraphsType::create(['id' => 'dictionary_word_entry', 'label' => 'Dictionary Word Entry'])->save();
    ParagraphsType::create(['id' => 'sample_sentence', 'label' => 'Sample Sentence'])->save();
    foreach (['language', 'keywords', 'word_type', 'contributor', 'location'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    FieldStorageConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'type' => 'geofield',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_coverage',
      'entity_type' => 'node',
      'bundle' => 'dictionary_word',
      'label' => 'Map Points',
    ])->save();
    Term::create(['name' => 'Test Language', 'vid' => 'language'])->save();

    $role = Role::load('authenticated');
    $role->grantPermission('create dictionary_word content');
    $role->save();

    $steward = OgRole::load('protocol-protocol-protocol_steward');
    $permissions = [
      'create dictionary_word content',
      'edit any dictionary_word content',
      'edit any word_list content',
      'edit any collection content',
    ];
    foreach ($permissions as $permission) {
      $steward->grantPermission($permission);
    }
    $steward->save();

    $this->wordList = $this->createContainer('word_list', 'List A');
  }

  /**
   * A new word is added to the word list, with the word list's log message.
   */
  public function testNewWordAdded(): void {
    $collection = $this->createContainer('collection', 'Collection A');

    $file = $this->createCsvFile([
      ['Title', 'Language', 'Protocols', 'Sharing Setting', 'Word Lists', 'Collections'],
      ['Word 1', 'Test Language', $this->protocol->id(), 'any', 'list a', 'Collection A'],
    ]);
    $result = $this->importCsvFile($file, [
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_dictionary_word_language', 'source' => 'Language'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
      ['target' => 'field_in_word_list', 'source' => 'Word Lists'],
      ['target' => 'field_in_collection', 'source' => 'Collections'],
    ], 'node', 'dictionary_word');
    $this->assertEquals(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertEmpty(iterator_to_array($this->lastMigration->getIdMap()->getMessages()));

    $words = $this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'dictionary_word', 'title' => 'Word 1']);
    $this->assertCount(1, $words);
    $word_id = (int) reset($words)->id();

    $this->assertSame([$word_id], $this->itemIds($this->wordList, 'field_words'));
    $this->assertSame([$word_id], $this->itemIds($collection, 'field_items_in_collection'));

    $storage = $this->entityTypeManager->getStorage('node');
    $latest = $storage->loadRevision($storage->getLatestRevisionId($this->wordList->id()));
    $this->assertSame('Added Word 1 to the word list.', $latest->getRevisionLogMessage());
  }

  /**
   * A value that names no word list fails the row with word list wording.
   */
  public function testUnknownWordListFailsRow(): void {
    $file = $this->createCsvFile([
      ['Title', 'Language', 'Protocols', 'Sharing Setting', 'Word Lists'],
      ['Word 1', 'Test Language', $this->protocol->id(), 'any', 'List B'],
    ]);
    $this->importCsvFile($file, [
      ['target' => 'title', 'source' => 'Title'],
      ['target' => 'field_dictionary_word_language', 'source' => 'Language'],
      ['target' => 'field_cultural_protocols/protocols', 'source' => 'Protocols'],
      ['target' => 'field_cultural_protocols/sharing_setting', 'source' => 'Sharing Setting'],
      ['target' => 'field_in_word_list', 'source' => 'Word Lists'],
    ], 'node', 'dictionary_word');

    $this->assertEmpty($this->entityTypeManager->getStorage('node')->loadByProperties(['type' => 'dictionary_word']));
    $messages = array_map(fn ($m) => (string) $m->message, iterator_to_array($this->lastMigration->getIdMap()->getMessages()));
    $this->assertSame([
      'No word list matches "List B". Use the word list\'s title, ID, or UUID.',
    ], $messages);
  }

  /**
   * Creates and saves a word list or collection in the test protocol.
   */
  protected function createContainer(string $bundle, string $title): NodeInterface {
    $node = Node::create([
      'type' => $bundle,
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Returns a container's item IDs, reloaded from storage.
   */
  protected function itemIds(NodeInterface $container, string $field): array {
    $container = $this->entityTypeManager->getStorage('node')->loadUnchanged($container->id());
    return array_map('intval', array_column($container->get($field)->getValue(), 'target_id'));
  }

}
