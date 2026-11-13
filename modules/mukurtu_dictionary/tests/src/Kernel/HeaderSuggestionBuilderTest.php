<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\mukurtu_browse\Service\HeaderSuggestionBuilder;
use Drupal\mukurtu_dictionary\Entity\WordList;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the items the header search suggests, grouped by content type.
 *
 * Lives with the dictionary tests because their base class already sets up
 * two content types under protocols. The index is built from the shipped
 * Browse index's fields and the shipped database server.
 */
#[CoversClass(HeaderSuggestionBuilder::class)]
#[Group('mukurtu_browse')]
#[Group('mukurtu_dictionary')]
class HeaderSuggestionBuilderTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_db',
  ];

  /**
   * A user in neither protocol's community.
   */
  protected UserInterface $outsider;

  /**
   * A member of the strict protocol.
   */
  protected UserInterface $member;

  /**
   * The one word list that matches.
   */
  protected WordList $wordList;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('search_api_task');
    $this->installConfig(['search_api']);

    $module_list = $this->container->get('extension.list.module');
    $server = (new FileStorage($module_list->getPath('mukurtu_core') . '/config/install'))
      ->read('search_api.server.mukurtu_database_server');
    $server['backend_config']['database'] = 'default:default';
    unset($server['dependencies']);
    Server::create($server)->save();

    $browse_fields = (new FileStorage($module_list->getPath('mukurtu_browse') . '/config/install'))
      ->read('search_api.index.mukurtu_default_content_index')['field_settings'];
    Index::create([
      'id' => 'header_suggestion_test',
      'name' => 'Header suggestion test index',
      'status' => TRUE,
      'server' => $server['id'],
      'datasource_settings' => [
        'entity:node' => ['bundles' => ['default' => TRUE, 'selected' => []]],
      ],
      'tracker_settings' => ['default' => []],
      'processor_settings' => [
        'content_access' => [],
        'entity_status' => [],
        'ignorecase' => [],
      ],
      'field_settings' => [
        'title' => $browse_fields['title'],
        'type' => $browse_fields['type'],
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

    foreach (['Quokka one', 'Quokka two', 'Quokka three', 'Quokka four', 'Wombat'] as $title) {
      $this->buildDictionaryWord($title)->save();
    }
    $this->wordList = $this->buildWordList('Quokka list');
    $this->wordList->save();
    $restricted = $this->buildDictionaryWord('Quokka secret');
    $restricted->setProtocols([$strict]);
    $restricted->save();

    Index::load('header_suggestion_test')->indexItems();
  }

  /**
   * Returns the header suggestions a user gets for some keys.
   */
  protected function suggest(UserInterface $account, string $keys, bool $link_groups = TRUE): array {
    $this->container->get('current_user')->setAccount($account);
    $query = Index::load('header_suggestion_test')->query();
    $query->keys($keys);
    return $this->container->get('mukurtu_browse.header_suggestion_builder')->build($query, $keys, $link_groups);
  }

  /**
   * Returns the labels of a group's items.
   */
  protected function labels(array $group): array {
    return array_column($group['items'], 'label');
  }

  /**
   * Items are grouped by content type, largest group first, three at most.
   */
  public function testGroups(): void {
    $groups = $this->suggest($this->outsider, 'quokka');

    $this->assertSame(['Dictionary Word', 'Word List'], array_column($groups, 'label'));
    $this->assertSame([4, 1], array_column($groups, 'count'));
    $this->assertCount(HeaderSuggestionBuilder::ITEMS_PER_GROUP, $groups[0]['items']);
    $this->assertSame(['Quokka list'], $this->labels($groups[1]));
    $this->assertSame($this->wordList->toUrl()->toString(), $groups[1]['items'][0]['url']);
    $this->assertSame([], $this->suggest($this->outsider, 'platypus'));
  }

  /**
   * Content in a protocol the viewer can't see is neither listed nor counted.
   */
  public function testRestrictedItemsAreHidden(): void {
    $outsider_words = $this->suggest($this->outsider, 'quokka')[0];
    $this->assertSame(4, $outsider_words['count']);
    $this->assertNotContains('Quokka secret', $this->labels($outsider_words));
    $this->assertSame([], $this->suggest($this->outsider, 'secret'));

    $this->assertSame(5, $this->suggest($this->member, 'quokka')[0]['count']);
    $member_secret = $this->suggest($this->member, 'secret');
    $this->assertSame(['Quokka secret'], $this->labels($member_secret[0]));
  }

  /**
   * A group links to more of its type only when it has more than it shows.
   */
  public function testMoreLinks(): void {
    [$words, $lists] = $this->suggest($this->outsider, 'quokka');

    parse_str((string) parse_url($words['more_url'], PHP_URL_QUERY), $query);
    $this->assertStringStartsWith('/browse?', $words['more_url']);
    $this->assertSame('quokka', $query['search_api_fulltext']);
    $this->assertSame(['dictionary_word' => 'dictionary_word'], $query['type']);
    $this->assertNull($lists['more_url']);

    $this->assertNull($this->suggest($this->outsider, 'quokka', FALSE)[0]['more_url']);
  }

}
