<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40040() (#2521).
 *
 * @see mukurtu_update_40040()
 */
#[Group('mukurtu')]
class ManageContentNestedTermFilterUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'node', 'taxonomy', 'views'];

  /**
   * The shipped Category and Keywords filters, keyed by filter ID.
   */
  protected array $shippedFilters;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    require_once $this->root . '/' . $profile_path . '/mukurtu.install';

    $shipped = (new FileStorage($profile_path . '/config/install'))->read('views.view.mukurtu_manage_all_content');
    $this->shippedFilters = array_intersect_key(
      $shipped['display']['default']['display_options']['filters'],
      array_flip(['field_category_target_id', 'field_keywords_target_id'])
    );
  }

  /**
   * Stores a view with the filters as they shipped before this update.
   *
   * Config is written directly, since the view's other handlers need modules
   * this test doesn't load.
   */
  protected function storeOldView(array $overrides = []): void {
    $filters = [];
    foreach ($this->shippedFilters as $id => $filter) {
      $filters[$id] = array_diff_key($filter, ['depth' => TRUE, 'entity_type' => TRUE]);
      $filters[$id]['table'] = 'taxonomy_index';
      $filters[$id]['field'] = 'tid';
      $filters[$id]['plugin_id'] = 'taxonomy_index_tid';
      $filters[$id]['hierarchy'] = FALSE;
    }
    $filters = array_replace_recursive($filters, $overrides);
    $this->config('views.view.mukurtu_manage_all_content')
      ->set('id', 'mukurtu_manage_all_content')
      ->set('label', 'Mukurtu Manage All Content')
      ->set('base_table', 'node_field_data')
      ->set('base_field', 'nid')
      ->set('display.default.id', 'default')
      ->set('display.default.display_plugin', 'default')
      ->set('display.default.display_title', 'Default')
      ->set('display.default.position', 0)
      ->set('display.default.display_options.filters', $filters)
      ->save();
  }

  /**
   * Gets a stored filter.
   */
  protected function storedFilter(string $id): array {
    return $this->config('views.view.mukurtu_manage_all_content')->get("display.default.display_options.filters.$id");
  }

  /**
   * The shipped filters are the nested-term ones.
   */
  public function testShippedFiltersIncludeNestedTerms(): void {
    foreach ($this->shippedFilters as $id => $filter) {
      $this->assertSame('taxonomy_index_tid_depth', $filter['plugin_id'], $id);
      $this->assertSame('term_node_tid_depth', $filter['field'], $id);
      $this->assertTrue($filter['hierarchy'], $id);
      $this->assertSame(10, $filter['depth'], $id);
    }
  }

  /**
   * Both filters are switched, and running it again changes nothing.
   */
  public function testSwitchesShippedFilters(): void {
    $this->storeOldView();

    $message = mukurtu_update_40040();

    $this->assertSame($this->shippedFilters['field_category_target_id'], $this->storedFilter('field_category_target_id'));
    $this->assertSame($this->shippedFilters['field_keywords_target_id'], $this->storedFilter('field_keywords_target_id'));
    $this->assertStringContainsString('now include nested terms', (string) $message);
    $this->assertNull(mukurtu_update_40040());
  }

  /**
   * A filter a site already changed is left alone.
   */
  public function testLeavesChangedFiltersAlone(): void {
    $this->storeOldView();
    $customised = ['depth' => 2] + $this->shippedFilters['field_keywords_target_id'];
    $this->config('views.view.mukurtu_manage_all_content')
      ->set('display.default.display_options.filters.field_keywords_target_id', $customised)
      ->save();

    mukurtu_update_40040();

    $this->assertSame($this->shippedFilters['field_category_target_id'], $this->storedFilter('field_category_target_id'));
    $this->assertSame(2, $this->storedFilter('field_keywords_target_id')['depth']);
  }

  /**
   * A site without the view is left alone.
   */
  public function testMissingView(): void {
    $this->assertNull(mukurtu_update_40040());
  }

}
