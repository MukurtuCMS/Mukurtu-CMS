<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_solr\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_solr_update_40005(), which gives the Solr-backed Browse,
 * Digital Heritage, and Dictionary search boxes the same page-specific
 * placeholder text as their DB-backed counterparts (#2018).
 *
 * @see mukurtu_solr_update_40005()
 * @group mukurtu_solr
 */
class SolrSearchPlaceholderUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The hook writes a partial view-config fixture, not full data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_solr');
    require_once $module_path . '/mukurtu_solr.install';
  }

  /**
   * Fixture config for a view with the search_api_fulltext exposed filter.
   */
  protected function fulltextFilterFixture(): array {
    return [
      'display' => [
        'default' => [
          'display_options' => [
            'filters' => [
              'search_api_fulltext' => [
                'expose' => [
                  'placeholder' => '',
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * The hook sets a distinct placeholder on all three Solr-backed views.
   */
  public function testUpdateSetsPlaceholderOnAllViews(): void {
    $expected = [
      'views.view.mukurtu_browse_solr' => 'Search all content',
      'views.view.mukurtu_digital_heritage_browse_solr' => 'Search digital heritage items',
      'views.view.mukurtu_dictionary_solr' => 'Search dictionary entries',
    ];

    foreach (array_keys($expected) as $name) {
      \Drupal::configFactory()->getEditable($name)
        ->setData($this->fulltextFilterFixture())
        ->save();
    }

    mukurtu_solr_update_40005();

    foreach ($expected as $name => $placeholder) {
      $this->assertSame(
        $placeholder,
        \Drupal::config($name)
          ->get('display.default.display_options.filters.search_api_fulltext.expose.placeholder'),
        "placeholder for $name"
      );
    }
  }

  /**
   * The update hook skips a view that doesn't exist on a non-Solr site.
   */
  public function testUpdateSkipsMissingViews(): void {
    $this->assertTrue(\Drupal::config('views.view.mukurtu_browse_solr')->isNew());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_digital_heritage_browse_solr')->isNew());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_dictionary_solr')->isNew());

    mukurtu_solr_update_40005();

    $this->assertTrue(\Drupal::config('views.view.mukurtu_browse_solr')->isNew());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_digital_heritage_browse_solr')->isNew());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_dictionary_solr')->isNew());
  }

}
