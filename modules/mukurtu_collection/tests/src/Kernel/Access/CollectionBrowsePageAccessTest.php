<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel\Access;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\mukurtu_collection\Controller\MukurtuBrowseCollectionsController;
use Drupal\Tests\mukurtu_collection\Kernel\CollectionTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests who can reach the Collections page.
 *
 * The page opens when the browse view has results. When it doesn't, it opens
 * only for users who can create collections.
 */
#[CoversClass(MukurtuBrowseCollectionsController::class)]
#[Group('mukurtu_collection')]
class CollectionBrowsePageAccessTest extends CollectionTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // The shipped view needs a Search API index. The access check only asks
    // whether the view has results, so a plain node view with the same ID
    // stands in for it.
    View::create([
      'id' => 'mukurtu_browse_collections',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'filters' => [
              'type' => [
                'id' => 'type',
                'table' => 'node_field_data',
                'field' => 'type',
                'plugin_id' => 'bundle',
                'entity_type' => 'node',
                'entity_field' => 'type',
                'value' => ['collection' => 'collection'],
              ],
              'status' => [
                'id' => 'status',
                'table' => 'node_field_data',
                'field' => 'status',
                'plugin_id' => 'boolean',
                'entity_type' => 'node',
                'entity_field' => 'status',
                'value' => '1',
              ],
            ],
            // Leave node access out of it; this tests the controller's own
            // check, not which collections a user can see.
            'query' => ['type' => 'views_query', 'options' => ['disable_sql_rewrite' => TRUE]],
          ],
        ],
      ],
    ])->save();

    $this->container->get('router.builder')->rebuild();
  }

  /**
   * Checks whether a new user with these permissions can reach the page.
   */
  protected function canAccess(array $permissions): bool {
    return $this->canAccessAs($this->createUser($permissions));
  }

  /**
   * Checks whether an account can reach the Collections page.
   */
  protected function canAccessAs(AccountInterface $account): bool {
    return $this->container->get('access_manager')
      ->checkNamedRoute('mukurtu_collection.collection_browse_page', [], $account);
  }

  /**
   * With no collections, only users who can create one get the page.
   */
  public function testEmptyPage(): void {
    $this->assertTrue($this->canAccess(['access content', 'create collection content']));
    $this->assertFalse($this->canAccess(['access content']));
  }

  /**
   * A protocol steward can create collections, so gets the empty page.
   *
   * This is how most Mukurtu users get create access: through a protocol
   * role, not a site-wide permission.
   */
  public function testEmptyPageForProtocolSteward(): void {
    $steward = $this->createUser(['access content']);
    $this->protocol->addMember($steward, ['protocol_steward']);

    $this->assertTrue($this->canAccessAs($steward));
  }

  /**
   * A cached 403 is cleared when a collection becomes visible.
   *
   * Anonymous visitors get the 403 from the page cache, which only expires
   * through cache tags. Creating a collection clears all cached 403s, but
   * publishing an existing one doesn't, so the access result has to carry
   * the view's tags.
   */
  public function testDeniedResultIsInvalidatedByPublishing(): void {
    $draft = $this->buildCollection('Draft collection');
    $draft->setUnpublished()->save();

    $result = $this->container->get('access_manager')
      ->checkNamedRoute('mukurtu_collection.collection_browse_page', [], $this->createUser(['access content']), TRUE);
    $this->assertTrue($result->isForbidden());
    $this->assertContains('user', $result->getCacheContexts());

    $cache = $this->container->get('cache.default');
    $cache->set('collections_403', TRUE, CacheBackendInterface::CACHE_PERMANENT, $result->getCacheTags());
    $this->assertNotFalse($cache->get('collections_403'));

    $draft->setPublished()->save();
    $this->assertFalse($cache->get('collections_403'));
    $this->assertTrue($this->canAccess(['access content']));
  }

  /**
   * With collections to show, anyone with access content gets the page.
   */
  public function testPageWithCollections(): void {
    $this->buildCollection('A collection')->save();

    $this->assertTrue($this->canAccess(['access content']));
  }

}
