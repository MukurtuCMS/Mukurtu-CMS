<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40045().
 *
 * The update hook removes facets_field_glossary_entry, which duplicated the
 * standalone glossary_entry facets.facet entity: same underlying field, same
 * facet source, but wired up a second time as a hidden-label exposed filter
 * on the view itself. The fixtures here skip real facets_filter/bef_links
 * plugin config (which would pull in the facets_exposed_filters and
 * better_exposed_filters module schemas) since the hook only cares about the
 * two array keys, not their contents; strictConfigSchema is disabled for the
 * same reason project memory notes for the #2079 fallback-test fix.
 *
 * @see mukurtu_dictionary_update_40045()
 */
#[Group('mukurtu_dictionary')]
class GlossaryEntryDuplicateFacetUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user', 'views'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Required directly rather than via loadInclude(): mukurtu_dictionary is
    // not enabled here, and loadInclude() cannot resolve a profile-nested
    // module that is switched off.
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary');
    require_once \Drupal::root() . '/' . $module_path . '/mukurtu_dictionary.install';
  }

  /**
   * Creates a mukurtu_dictionary view with the pre-fix duplicate filter shape.
   */
  private function makeView(): View {
    $view = View::create([
      'id' => 'mukurtu_dictionary',
      'label' => 'Mukurtu Dictionary',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'filters' => [
              'facets_word_lists' => [
                'id' => 'facets_word_lists',
                'table' => 'search_api_index_mukurtu_dictionary_index',
                'field' => 'facets_word_lists',
                'plugin_id' => 'standard',
              ],
              'facets_field_glossary_entry' => [
                'id' => 'facets_field_glossary_entry',
                'table' => 'search_api_index_mukurtu_dictionary_index',
                'field' => 'facets_field_glossary_entry',
                'plugin_id' => 'standard',
              ],
            ],
            'exposed_form' => [
              'type' => 'basic',
              'options' => [
                'bef' => [
                  'filter' => [
                    'facets_word_lists' => [
                      'plugin_id' => 'default',
                    ],
                    'facets_field_glossary_entry' => [
                      'plugin_id' => 'bef_links',
                      'hide_label' => TRUE,
                    ],
                  ],
                ],
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    return $view;
  }

  /**
   * The duplicate filter and its BEF options are removed, its neighbor kept.
   */
  public function testRemovesDuplicateFilter(): void {
    $this->makeView();

    mukurtu_dictionary_update_40045();

    $view = View::load('mukurtu_dictionary');
    $display_options = $view->get('display')['default']['display_options'];
    $filters = $display_options['filters'];
    $bef_filters = $display_options['exposed_form']['options']['bef']['filter'];

    $this->assertArrayNotHasKey('facets_field_glossary_entry', $filters);
    $this->assertArrayNotHasKey('facets_field_glossary_entry', $bef_filters);
    $this->assertArrayHasKey('facets_word_lists', $filters, 'An unrelated filter was removed too.');
    $this->assertArrayHasKey('facets_word_lists', $bef_filters, 'An unrelated BEF option was removed too.');
  }

  /**
   * Running it twice is harmless.
   */
  public function testIsIdempotent(): void {
    $this->makeView();

    mukurtu_dictionary_update_40045();
    mukurtu_dictionary_update_40045();

    $view = View::load('mukurtu_dictionary');
    $filters = $view->get('display')['default']['display_options']['filters'];
    $this->assertArrayNotHasKey('facets_field_glossary_entry', $filters);
  }

  /**
   * A site with no mukurtu_dictionary view at all does not error.
   */
  public function testMissingViewDoesNotError(): void {
    $this->assertNull(View::load('mukurtu_dictionary'));
    mukurtu_dictionary_update_40045();
    $this->assertNull(View::load('mukurtu_dictionary'));
  }

  /**
   * A view that never had the duplicate filter is left untouched.
   */
  public function testViewWithoutDuplicateFilterIsUntouched(): void {
    $view = View::create([
      'id' => 'mukurtu_dictionary',
      'label' => 'Mukurtu Dictionary',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_options' => [
            'filters' => [
              'facets_word_lists' => [
                'id' => 'facets_word_lists',
                'table' => 'search_api_index_mukurtu_dictionary_index',
                'field' => 'facets_word_lists',
                'plugin_id' => 'standard',
              ],
            ],
          ],
        ],
      ],
    ]);
    $view->save();

    mukurtu_dictionary_update_40045();

    $view = View::load('mukurtu_dictionary');
    $filters = $view->get('display')['default']['display_options']['filters'];
    $this->assertArrayHasKey('facets_word_lists', $filters);
  }

}
