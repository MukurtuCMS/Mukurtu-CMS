<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\Core\Form\FormState;
use Drupal\mukurtu_browse\Controller\SearchSuggestController;
use Drupal\mukurtu_browse\Hook\SearchSuggestionHooks;
use Drupal\mukurtu_search\Form\SearchSettingsForm;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the settings that turn search suggestions off.
 *
 * Lives with the dictionary tests because their base class already enables
 * Mukurtu Browse and its dependencies.
 */
#[CoversClass(SearchSuggestionHooks::class)]
#[CoversClass(SearchSuggestController::class)]
#[CoversClass(SearchSettingsForm::class)]
#[Group('mukurtu_browse')]
#[Group('mukurtu_search')]
class SearchSuggestionSettingsTest extends DictionaryTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'search_api_autocomplete',
    'search_api_db',
  ];

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

    Index::create([
      'id' => 'suggestion_settings_test',
      'name' => 'Suggestion settings test index',
      'status' => TRUE,
      'server' => $server['id'],
      'datasource_settings' => [
        'entity:node' => ['bundles' => ['default' => TRUE, 'selected' => []]],
      ],
      'tracker_settings' => ['default' => []],
    ])->save();

    // Access only needs an enabled search on an enabled index. Saving a
    // Search entity requires a search plugin (a view), so the config record
    // is written directly instead.
    $this->config('search_api_autocomplete.search.mukurtu_browse')->setData([
      'langcode' => 'en',
      'status' => TRUE,
      'dependencies' => [],
      'id' => 'mukurtu_browse',
      'label' => 'Browse',
      'index_id' => 'suggestion_settings_test',
      'suggester_settings' => [],
      'suggester_weights' => [],
      'suggester_limits' => [],
      'search_settings' => [],
      'options' => ['min_length' => 3],
    ])->save();

    $role = Role::create(['id' => 'searcher', 'label' => 'Searcher']);
    $role->grantPermission('use search_api_autocomplete for mukurtu_browse');
    $role->save();
    $searcher = User::create(['name' => 'searcher', 'roles' => ['searcher']]);
    $searcher->save();
    $this->container->get('current_user')->setAccount($searcher);

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Sets one Mukurtu search setting.
   */
  protected function setSearchSetting(string $key, bool $value): void {
    $this->config('mukurtu_search.settings')->set($key, $value)->save();
  }

  /**
   * Runs the exposed form alter on a Browse search box and returns the box.
   */
  protected function alteredSearchBox(): array {
    $form = [
      'search_api_fulltext' => [
        '#type' => 'search_api_autocomplete',
        '#search_id' => 'mukurtu_browse',
        '#additional_data' => [
          'display' => 'mukurtu_browse_block',
          'filter' => 'search_api_fulltext',
        ],
      ],
    ];
    $this->container->get('class_resolver')
      ->getInstanceFromDefinition(SearchSuggestionHooks::class)
      ->formViewsExposedFormAlter($form, new FormState());
    return $form['search_api_fulltext'];
  }

  /**
   * Runs the header search preprocess and returns its variables.
   */
  protected function preprocessedHeader(): array {
    $variables = ['plugin_id' => 'system_menu_block:main'];
    $this->container->get('class_resolver')
      ->getInstanceFromDefinition(SearchSuggestionHooks::class)
      ->preprocessBlock($variables);
    return $variables;
  }

  /**
   * Word suggestions are on by default and the setting turns them off.
   */
  public function testWordSuggestionsSetting(): void {
    $box = $this->alteredSearchBox();
    $this->assertSame('textfield', $box['#type']);
    $this->assertArrayHasKey('data-mukurtu-search-suggest', $box['#attributes']);
    $this->assertContains('mukurtu_browse/search-suggest', $box['#attached']['library']);

    $this->setSearchSetting('search_suggestions_enabled', FALSE);
    $box = $this->alteredSearchBox();
    // The module's own dropdown must not come back either.
    $this->assertSame('textfield', $box['#type']);
    $this->assertArrayNotHasKey('data-mukurtu-search-suggest', $box['#attributes'] ?? []);
    $this->assertNotContains('mukurtu_browse/search-suggest', $box['#attached']['library'] ?? []);
    // Saving the setting must clear pages that show the box.
    $this->assertContains('config:mukurtu_search.settings', $box['#cache']['tags']);
  }

  /**
   * Header suggestions are on by default and the setting turns them off.
   */
  public function testHeaderSuggestionsSetting(): void {
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(SearchSuggestController::class);
    $account = $this->container->get('current_user');

    $this->assertTrue($controller->access($account)->isAllowed());
    $this->assertArrayHasKey('header_suggest_url', $this->preprocessedHeader());

    $this->setSearchSetting('header_suggestions_enabled', FALSE);
    $access = $controller->access($account);
    $this->assertFalse($access->isAllowed());
    $this->assertContains('config:mukurtu_search.settings', $access->getCacheTags());
    $variables = $this->preprocessedHeader();
    $this->assertArrayNotHasKey('header_suggest_url', $variables);
    $this->assertContains('config:mukurtu_search.settings', $variables['#cache']['tags']);

    // Word suggestions are a separate setting.
    $this->assertArrayHasKey('data-mukurtu-search-suggest', $this->alteredSearchBox()['#attributes']);
  }

  /**
   * The settings form saves both switches.
   */
  public function testSettingsFormSavesSwitches(): void {
    // A programmed submit reads FALSE as "not submitted" and keeps the
    // default, so unchecked boxes are NULL.
    $form_state = (new FormState())->setValues([
      'backend' => 'db',
      'collapse_multipage_pages' => 1,
      'collapse_community_records' => NULL,
      'search_suggestions_enabled' => NULL,
      'header_search_enabled' => 1,
      'header_search_path' => '/browse',
      'header_suggestions_enabled' => NULL,
      'op' => 'Save configuration',
    ]);
    $this->container->get('form_builder')->submitForm(SearchSettingsForm::class, $form_state);
    $this->assertEmpty($form_state->getErrors());

    $config = $this->config('mukurtu_search.settings');
    $this->assertFalse($config->get('search_suggestions_enabled'));
    $this->assertFalse($config->get('header_suggestions_enabled'));
  }

}
