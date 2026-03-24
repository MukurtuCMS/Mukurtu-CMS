<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Tests\mukurtu_core\Kernel\MukurtuKernelTestBase;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;

/**
 * Base class for Mukurtu Dictionary kernel tests.
 */
abstract class DictionaryTestBase extends MukurtuKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'blazy',
    'block_content',
    'comment',
    'content_moderation',
    'entity_browser',
    'entity_reference_revisions',
    'facets',
    'field',
    'field_group',
    'file',
    'filter',
    'geofield',
    'image',
    'layout_builder',
    'link',
    'media',
    'media_library',
    'menu_ui',
    'node',
    'node_access_test',
    'og',
    'options',
    'paragraphs',
    'path',
    'path_alias',
    'search_api',
    'search_api_glossary',
    'system',
    'tagify',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
    'mukurtu_browse',
    'mukurtu_collection',
    'mukurtu_core',
    'mukurtu_dictionary',
    'mukurtu_drafts',
    'mukurtu_local_contexts',
    'mukurtu_protocol',
    'mukurtu_search',
    'mukurtu_taxonomy',
  ];

  /**
   * A language taxonomy term required for dictionary words.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected Term $language;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('comment', 'comment_entity_statistics');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item', 'search_api_task']);

    // Grant dictionary node permissions to the authenticated role so that
    // bundleCheckCreateAccess() passes for logged-in users.
    $role = Role::load('authenticated');
    $role->grantPermission('create dictionary_word content');
    $role->grantPermission('create word_list content');
    $role->save();

    $this->installEntitySchema('comment');
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('search_api_index');
    $this->installEntitySchema('search_api_server');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('language_community');

    // Create the node type bundles so hook_entity_bundle_info_alter in
    // mukurtu_dictionary assigns DictionaryWord and WordList bundle classes.
    NodeType::create(['type' => 'dictionary_word', 'name' => 'Dictionary Word'])->save();
    NodeType::create(['type' => 'word_list', 'name' => 'Word List'])->save();

    // Create paragraph type bundles for DictionaryWordEntry and SampleSentence.
    ParagraphsType::create(['id' => 'dictionary_word_entry', 'label' => 'Dictionary Word Entry'])->save();
    ParagraphsType::create(['id' => 'sample_sentence', 'label' => 'Sample Sentence'])->save();

    node_access_rebuild();

    // Vocabularies required by dictionary word fields.
    Vocabulary::create(['vid' => 'language', 'name' => 'Language'])->save();
    Vocabulary::create(['vid' => 'keywords', 'name' => 'Keywords'])->save();
    Vocabulary::create(['vid' => 'word_type', 'name' => 'Word Type'])->save();
    Vocabulary::create(['vid' => 'contributor', 'name' => 'Contributor'])->save();
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();

    // Create at least one language term. DictionaryWord::bundleCheckCreateAccess
    // requires at least one language to exist.
    $this->language = Term::create(['name' => 'Test Language', 'vid' => 'language']);
    $this->language->save();
  }

  /**
   * {@inheritdoc}
   *
   * Add dictionary-specific node CRUD permissions to the protocol steward role.
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), [
      'create dictionary_word node',
      'delete any dictionary_word node',
      'delete own dictionary_word node',
      'update any dictionary_word node',
      'update own dictionary_word node',
    ]);
  }

  /**
   * Build an unsaved DictionaryWord node with protocol and language set.
   *
   * @param string $title
   *   The word title.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\DictionaryWord
   */
  protected function buildDictionaryWord(string $title): DictionaryWord {
    /** @var \Drupal\mukurtu_dictionary\Entity\DictionaryWord $word */
    $word = Node::create([
      'type' => 'dictionary_word',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
      'field_dictionary_word_language' => [['target_id' => $this->language->id()]],
    ]);
    $word->setSharingSetting('any');
    $word->setProtocols([$this->protocol]);
    return $word;
  }

  /**
   * Build an unsaved WordList node with protocol set.
   *
   * @param string $title
   *   The word list title.
   *
   * @return \Drupal\mukurtu_dictionary\Entity\WordList
   */
  protected function buildWordList(string $title): WordList {
    /** @var \Drupal\mukurtu_dictionary\Entity\WordList $list */
    $list = Node::create([
      'type' => 'word_list',
      'title' => $title,
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $list->setSharingSetting('any');
    $list->setProtocols([$this->protocol]);
    return $list;
  }

}
