<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\og\Entity\OgRole;
use Drupal\og\Og;
use Drupal\paragraphs\Entity\ParagraphsType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Base class for Mukurtu Dictionary kernel tests.
 */
abstract class DictionaryTestBase extends KernelTestBase {

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
   * The current test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected User $currentUser;

  /**
   * A community for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Community
   */
  protected Community $community;

  /**
   * A protocol for the content.
   *
   * @var \Drupal\mukurtu_protocol\Entity\Protocol
   */
  protected Protocol $protocol;

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

    $this->installSchema('system', 'sequences');
    $this->installSchema('node', ['node_access']);
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installSchema('mukurtu_local_contexts', [
      'mukurtu_local_contexts_supported_projects',
      'mukurtu_local_contexts_projects',
      'mukurtu_local_contexts_labels',
      'mukurtu_local_contexts_label_translations',
      'mukurtu_local_contexts_notices',
      'mukurtu_local_contexts_notice_translations',
    ]);

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('paragraph');
    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('taxonomy_vocabulary');
    $this->installEntitySchema('og_membership');
    $this->installEntitySchema('workflow');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('language_community');

    $this->installConfig(['filter', 'og', 'system']);

    // Create the node type bundles so hook_entity_bundle_info_alter in
    // mukurtu_dictionary assigns DictionaryWord and WordList bundle classes.
    NodeType::create(['type' => 'dictionary_word', 'name' => 'Dictionary Word'])->save();
    NodeType::create(['type' => 'word_list', 'name' => 'Word List'])->save();

    // Create paragraph type bundles for DictionaryWordEntry and SampleSentence.
    ParagraphsType::create(['id' => 'dictionary_word_entry', 'label' => 'Dictionary Word Entry'])->save();
    ParagraphsType::create(['id' => 'sample_sentence', 'label' => 'Sample Sentence'])->save();

    node_access_rebuild();
    Og::addGroup('community', 'community');
    Og::addGroup('protocol', 'protocol');

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

    // Authenticated role.
    $role = Role::create(['id' => 'authenticated', 'label' => 'authenticated']);
    $role->grantPermission('access content');
    $role->grantPermission('create dictionary_word content');
    $role->grantPermission('create word_list content');
    $role->save();

    // Protocol steward OG role.
    $protocolStewardRole = OgRole::create([
      'name' => 'protocol_steward',
      'label' => 'Protocol Steward',
      'permissions' => [
        'add user',
        'apply protocol',
        'administer permissions',
        'approve and deny subscription',
        'create dictionary_word node',
        'delete any dictionary_word node',
        'delete own dictionary_word node',
        'update any dictionary_word node',
        'update own dictionary_word node',
        'manage members',
        'update group',
      ],
    ]);
    $protocolStewardRole->setGroupType('protocol');
    $protocolStewardRole->setGroupBundle('protocol');
    $protocolStewardRole->save();

    $this->container = \Drupal::getContainer();

    $user = User::create(['name' => $this->randomString()]);
    $user->save();
    $this->currentUser = $user;
    $this->container->get('current_user')->setAccount($user);

    $community = Community::create(['name' => 'Test Community']);
    $community->save();
    $community->addMember($user);
    $this->community = $community;

    $protocol = Protocol::create([
      'name' => 'Test Protocol',
      'field_communities' => [$community->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $protocol->addMember($user, ['protocol_steward']);
    $this->protocol = $protocol;
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
