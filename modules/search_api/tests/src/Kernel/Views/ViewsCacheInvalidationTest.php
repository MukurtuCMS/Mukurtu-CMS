<?php

namespace Drupal\Tests\search_api\Kernel\Views;

use Drupal\Core\Cache\Cache;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\search_api\Kernel\PostRequestIndexingTrait;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Tests\AssertViewsCacheTagsTrait;

// cspell:ignore angua littlebottom überwald
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests that cached Search API views get invalidated at the right occasions.
 *
 * @group search_api
 */
#[RunTestsInSeparateProcesses]
class ViewsCacheInvalidationTest extends KernelTestBase {

  use AssertViewsCacheTagsTrait;
  use PostRequestIndexingTrait;
  use UserCreationTrait;

  /**
   * The ID of the view used in the test.
   */
  const TEST_VIEW_ID = 'search_api_test_node_view';

  /**
   * The display ID used in the test.
   */
  const TEST_VIEW_DISPLAY_ID = 'page_1';

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The service that is responsible for creating Views executable objects.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewExecutableFactory;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  protected $cacheTagsInvalidator;

  /**
   * The current user service.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The search index used for testing.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $index;

  /**
   * Test users.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected $users;

  /**
   * A test content type.
   *
   * @var \Drupal\node\NodeTypeInterface
   */
  protected $contentType;

  /**
   * The test nodes, keyed by node title.
   *
   * @var \Drupal\node\NodeInterface[]
   */
  protected $nodes;

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'node',
    'rest',
    'search_api',
    'search_api_db',
    'search_api_test',
    'search_api_test_node_indexing',
    'search_api_test_views',
    'serialization',
    'system',
    'text',
    'user',
    'views',
    'views_test_data',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('search_api', ['search_api_item']);

    $this->installEntitySchema('node');
    $this->installEntitySchema('search_api_task');
    $this->installEntitySchema('user');

    $this->installConfig([
      'node',
      'search_api',
      'search_api_test_node_indexing',
      'search_api_test_views',
    ]);

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->viewExecutableFactory = $this->container->get('views.executable');
    $this->renderer = $this->container->get('renderer');
    $this->cacheTagsInvalidator = $this->container->get('cache_tags.invalidator');
    $this->currentUser = $this->container->get('current_user');
    $this->state = $this->container->get('state');

    // Use the test search index from the search_api_test_db module.
    $this->index = Index::load('test_node_index');

    // Create a test content type.
    $this->contentType = NodeType::create([
      'name' => 'Page',
      'type' => 'page',
    ]);
    $this->contentType->save();

    // Create some test content and index it.
    foreach (['Cheery' => TRUE, 'Carrot' => TRUE, 'Detritus' => FALSE] as $title => $status) {
      $this->createNode($title, $status);
    }
    $this->index->indexItems();

    // Create a dummy test user. This user will get UID 1 which is handled as
    // the root user and can bypass all access restrictions. This is not used
    // in the test.
    $this->createUser();

    // Create two test users, one with permission to view unpublished entities,
    // and one without.
    $this->users['no-access'] = $this->createUser(['access content']);
    $this->users['has-access'] = $this->createUser(['access content', 'bypass node access']);
  }

  /**
   * Tests that a cached views query result is invalidated at the right moments.
   */
  public function testQueryCacheInvalidation() {
    // We are testing two variants of the view, one for users that have
    // permission to view unpublished entities, and one for users that do not.
    // Initially both variants should be uncached.
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the user with the "bypass node access" permission can see all
    // 3 items.
    $this->assertViewsResult('has-access', ['Cheery', 'Carrot', 'Detritus']);

    // The result should now be cached for the privileged user.
    $this->assertNotCached('no-access');
    $this->assertCached('has-access');

    // Check that the user without the "bypass node access" permission can only
    // see the published items.
    $this->assertViewsResult('no-access', ['Cheery', 'Carrot']);

    // Both results should now be cached.
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Add another unpublished item.
    $this->createNode('Angua', FALSE);

    // Our search index is not configured to automatically index items, so just
    // creating a node should not invalidate the caches.
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Index the item, this should invalidate the caches.
    $this->index->indexItems();
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the user without the "bypass node access" permission can still
    // only see the published items.
    $this->assertViewsResult('no-access', ['Cheery', 'Carrot']);
    $this->assertCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the user with the "bypass node access" permission can see all
    // 4 items.
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Carrot', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Grant the permission to "bypass node access" to the unprivileged user.
    $privileged_role = $this->users['has-access']->getRoles()[1];
    $this->users['no-access']->addRole($privileged_role);
    $this->users['no-access']->save();

    // Changing the roles of a user should not affect the cached results. The
    // user will now have a new cache context, but the old context should still
    // be present for all other users that still have the same combination of
    // roles that our "no-access" user had before they were changed.
    // In fact, since our user now has the same set of roles as the "has-access"
    // user, the user will immediately benefit from the cached results that
    // already exist for the cache contexts of the "has-access" user.
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // The user should now be able to see all 4 items.
    $this->assertViewsResult('no-access', ['Angua', 'Cheery', 'Carrot', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Remove the role again from the unprivileged user. This also should not
    // affect cached results. The "no-access" user now switches back to only
    // being able to see the published items, and everything is still happily
    // cached.
    $this->users['no-access']->removeRole($privileged_role);
    $this->users['no-access']->save();
    $this->assertCached('no-access');
    $this->assertCached('has-access');
    $this->assertViewsResult('no-access', ['Cheery', 'Carrot']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Edit one of the test content entities. This should not affect the cached
    // view until the search index is updated.
    $this->nodes['Cheery']->set('title', 'Cheery Littlebottom')->save();
    $this->assertCached('no-access');
    $this->assertCached('has-access');
    $this->index->indexItems();
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // The view should show the updated title when displayed, and the result
    // should be cached.
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Carrot', 'Detritus']);
    $this->assertNotCached('no-access');
    $this->assertCached('has-access');
    $this->assertViewsResult('no-access', ['Cheery', 'Carrot']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Delete one of the test content entities. This takes effect immediately,
    // there is no need to wait until the search index is updated.
    // @see search_api_entity_delete()
    $this->nodes['Carrot']->delete();
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // The view should no longer include the deleted content now, and the result
    // should be cached after the view has been displayed.
    $this->assertViewsResult('no-access', ['Cheery']);
    $this->assertCached('no-access');
    $this->assertNotCached('has-access');
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Update the search index configuration so it will index items immediately
    // when they are created or updated.
    $this->index->setOption('index_directly', TRUE)->save();

    // Changing the configuration of the index should invalidate all views that
    // show its data.
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the expected results are still returned and are cacheable.
    $this->assertViewsResult('no-access', ['Cheery']);
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Change the configuration of the view. This should also invalidate all
    // displays of the view.
    $view = $this->getView();
    $view->setItemsPerPage(20);
    $view->save();
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the expected results are still returned and are cacheable.
    $this->assertViewsResult('no-access', ['Cheery']);
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Edit one of the test content entities. Because the search index is being
    // updated immediately, the cached views should be cleared without having to
    // perform a manual indexing step.
    $this->nodes['Angua']->set('title', 'Angua von Überwald')->save();
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');

    // Check that the updated results are shown and are cacheable.
    $this->assertViewsResult('no-access', ['Cheery']);
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');

    // Activate the alter hook and resave the view so it will recalculate the
    // cacheability metadata.
    $this->state->set('search_api_test_views.alter_query_cacheability_metadata', TRUE);
    $view = $this->getView();
    $view->save();
    // Populate the Views results cache.
    $this->assertViewsResult('no-access', ['Cheery']);
    $this->assertViewsResult('has-access', ['Angua', 'Cheery', 'Detritus']);
    $this->assertCached('no-access');
    $this->assertCached('has-access');
    // Make sure that the Views results cache is invalidated whenever the custom
    // cache tag that was added to the query is invalidated.
    $this->cacheTagsInvalidator->invalidateTags(['search_api:test_views_page:search_api_test_node_view__page_1']);
    $this->assertNotCached('no-access');
    $this->assertNotCached('has-access');
  }

  /**
   * Checks that the view is cached for the given user.
   *
   * @param string $user_key
   *   The key of the user for which to perform the check.
   */
  protected function assertCached($user_key) {
    $this->doAssertCached('assertNotEmpty', $user_key);
  }

  /**
   * Checks that the view is not cached for the given user.
   *
   * @param string $user_key
   *   The key of the user for which to perform the check.
   */
  protected function assertNotCached($user_key) {
    $this->doAssertCached('assertEmpty', $user_key);
  }

  /**
   * Checks the cache status of the view for the given user.
   *
   * @param string $assert_method
   *   The method to use for asserting that the view is cached or not cached.
   * @param int $user_key
   *   The key of the user for which to perform the check.
   */
  protected function doAssertCached($assert_method, $user_key) {
    // Ensure that any post request indexing is done. This is normally handled
    // at the end of the request but since we are running a KernelTest we are
    // not executing any requests and need to trigger this manually.
    $this->triggerPostRequestIndexing();

    // Set the user that will be used to check the cache status.
    $this->setCurrentUser($user_key);

    // Retrieve the cached data and perform the assertion.
    $view = $this->getView();
    $view->build();
    /** @var \Drupal\views\Plugin\views\cache\CachePluginBase $cache */
    $cache = $view->getDisplay()->getPlugin('cache');
    $cached_data = $cache->cacheGet('results');

    $this->$assert_method($cached_data);
  }

  /**
   * Checks that the view for the given user contains the expected results.
   *
   * @param string $user_key
   *   The key of the user to check.
   * @param string[] $node_titles
   *   The titles of the nodes that are expected to be present in the results.
   */
  protected function assertViewsResult($user_key, array $node_titles) {
    // Clear the static caches of the cache tags invalidators. The invalidators
    // will only invalidate cache tags once per request to improve performance.
    // Unfortunately they cannot distinguish between an actual Drupal page
    // request and a PHPUnit test that simulates visiting multiple pages.
    // We are pretending that every time this method is called a new page has
    // been requested, and the static caches are empty.
    $this->cacheTagsInvalidator->resetChecksums();

    $this->setCurrentUser($user_key);

    $render_array = $this->getRenderableView();
    $html = (string) $this->renderer->renderRoot($render_array);

    // Check that exactly the titles of the expected results are present.
    $node_titles = array_flip($node_titles);
    foreach ($this->nodes as $node_title => $node) {
      if (isset($node_titles[$node_title])) {
        $this->assertStringContainsString($node_title, $html);
      }
      else {
        $this->assertStringNotContainsString($node_title, $html);
      }
    }
  }

  /**
   * Sets the user with the given key as the currently active user.
   *
   * @param string $user_key
   *   The key of the user to set as currently active user.
   */
  protected function setCurrentUser($user_key) {
    $this->currentUser->setAccount($this->users[$user_key]);
  }

  /**
   * Returns the test view as a render array.
   *
   * @return array|null
   *   The render array, or NULL if the view cannot be rendered.
   */
  protected function getRenderableView() {
    $render_array = $this->getView()->buildRenderable();
    $renderer_config = $this->container->getParameter('renderer.config');
    $render_array['#cache']['contexts'] = Cache::mergeContexts(
      $render_array['#cache']['contexts'],
      $renderer_config['required_cache_contexts']
    );

    return $render_array;
  }

  /**
   * Returns the test view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The view.
   */
  protected function getView() {
    /** @var \Drupal\views\ViewEntityInterface $view */
    $view = $this->entityTypeManager->getStorage('view')
      ->load(self::TEST_VIEW_ID);
    $executable = $this->viewExecutableFactory->get($view);
    $executable->setDisplay(self::TEST_VIEW_DISPLAY_ID);

    return $executable;
  }

  /**
   * Creates a node with the given title and publication status.
   *
   * @param string $title
   *   The title for the node.
   * @param bool $status
   *   The publication status to set.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if an error occurred during the saving of the node.
   */
  protected function createNode($title, $status) {
    $values = [
      'title' => $title,
      'status' => $status,
      'type' => $this->contentType->id(),
    ];
    $this->nodes[$title] = Node::create($values);
    $this->nodes[$title]->save();
  }

}
