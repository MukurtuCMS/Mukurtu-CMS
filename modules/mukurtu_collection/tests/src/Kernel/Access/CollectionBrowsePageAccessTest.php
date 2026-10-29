<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel\Access;

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
   * Checks whether an account can reach the Collections page.
   */
  protected function canAccess(array $permissions): bool {
    $account = $this->createUser($permissions);
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
   * With collections to show, anyone with access content gets the page.
   */
  public function testPageWithCollections(): void {
    $this->buildCollection('A collection')->save();

    $this->assertTrue($this->canAccess(['access content']));
  }

}
