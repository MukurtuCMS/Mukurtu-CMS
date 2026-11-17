<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api_autocomplete\Entity\Search;
use Drupal\search_api_autocomplete\Plugin\search_api_autocomplete\suggester\Server as ServerSuggester;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the words the dictionary search box suggests as people type.
 *
 * The index and server are built from the shipped config so the test covers
 * the fields and backend settings sites actually get, and suggestions come
 * from search_api_autocomplete's own server suggester.
 */
#[CoversClass(ServerSuggester::class)]
#[Group('mukurtu_dictionary')]
class DictionarySearchSuggestionTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_autocomplete',
    'search_api_db',
  ];

  /**
   * A user in neither of the word protocols' communities.
   */
  protected UserInterface $outsider;

  /**
   * A member of the strict protocol.
   */
  protected UserInterface $member;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api']);

    $module_list = $this->container->get('extension.list.module');
    $core_config = new FileStorage($module_list->getPath('mukurtu_core') . '/config/install');
    $dictionary_config = new FileStorage($module_list->getPath('mukurtu_dictionary') . '/config/install');

    // The shipped server, for its backend and autocomplete settings.
    $server = $core_config->read('search_api.server.mukurtu_database_server');
    $server['backend_config']['database'] = 'default:default';
    unset($server['dependencies']);
    Server::create($server)->save();

    $shipped_fields = $dictionary_config->read('search_api.index.mukurtu_dictionary_index')['field_settings'];
    Index::create([
      'id' => 'dictionary_suggestion_test',
      'name' => 'Dictionary suggestion test index',
      'status' => TRUE,
      'server' => $server['id'],
      'datasource_settings' => [
        'entity:node' => ['bundles' => ['default' => FALSE, 'selected' => ['dictionary_word']]],
      ],
      'tracker_settings' => ['default' => []],
      'processor_settings' => [
        'aggregated_field' => [],
        'content_access' => [],
        'entity_status' => [],
        'ignorecase' => [],
      ],
      'field_settings' => [
        'title' => $shipped_fields['title'],
        'dictionary_fulltext' => $shipped_fields['dictionary_fulltext'],
      ],
      'options' => ['index_directly' => FALSE],
    ])->save();

    $strict = Protocol::create([
      'name' => 'Strict',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();

    $this->outsider = User::create(['name' => 'outsider']);
    $this->outsider->save();
    $this->member = User::create(['name' => 'member']);
    $this->member->save();
    $this->community->addMember($this->member);
    $strict->addMember($this->member);

    $open = $this->buildDictionaryWord('Openhead');
    $open->set('field_translation', 'zebrafish');
    $open->set('field_alternate_spelling', 'quokkaland');
    $open->set('field_definition', 'zirconium');
    $open->save();

    $restricted = $this->buildDictionaryWord('Strictheadword');
    $restricted->set('field_translation', 'zebrawood');
    $restricted->setProtocols([$strict]);
    $restricted->save();

    Index::load('dictionary_suggestion_test')->indexItems();
  }

  /**
   * Returns the words suggested to a user for what they have typed.
   *
   * @return string[]
   *   The suggested search keys, sorted.
   */
  protected function suggest(UserInterface $account, string $typed): array {
    $this->container->get('current_user')->setAccount($account);

    // The shipped dictionary search, minus its Views plugin, which would need
    // the whole dictionary view.
    $shipped = (new FileStorage($this->container->get('extension.list.module')->getPath('mukurtu_dictionary') . '/config/install'))
      ->read('search_api_autocomplete.search.mukurtu_dictionary');
    $search = Search::create([
      'id' => 'dictionary_suggestion_test',
      'index_id' => 'dictionary_suggestion_test',
      'suggester_settings' => $shipped['suggester_settings'],
      'options' => $shipped['options'],
    ]);

    $query = Index::load('dictionary_suggestion_test')->query();
    $suggestions = $search->getSuggester('server')->getAutocompleteSuggestions($query, $typed, $typed);
    $keys = array_map(fn ($suggestion) => $suggestion->getSuggestedKeys(), $suggestions);
    sort($keys);
    return $keys;
  }

  /**
   * Words from a protocol the viewer can't see are never suggested.
   */
  public function testRestrictedWordsAreNotSuggested(): void {
    $this->assertSame(['zebrafish'], $this->suggest($this->outsider, 'zeb'));
    $this->assertSame(['zebrafish', 'zebrawood'], $this->suggest($this->member, 'zeb'));
    $this->assertSame([], $this->suggest($this->outsider, 'stri'));
    $this->assertSame(['strictheadword'], $this->suggest($this->member, 'stri'));
  }

  /**
   * Words come from the term and from every field the dictionary searches.
   */
  public function testSuggestedFields(): void {
    $this->assertSame(['openhead'], $this->suggest($this->outsider, 'openh'));
    $this->assertSame(['quokkaland'], $this->suggest($this->outsider, 'quo'));
    $this->assertSame(['zirconium'], $this->suggest($this->outsider, 'zir'));
  }

}
