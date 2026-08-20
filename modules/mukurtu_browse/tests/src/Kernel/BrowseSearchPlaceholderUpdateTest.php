<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_browse\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests mukurtu_browse_update_40019(), which gives the Browse and Digital
 * Heritage search boxes page-specific placeholder text instead of the
 * generic "Enter keywords" (#2018).
 *
 * @see mukurtu_browse_update_40019()
 * @group mukurtu_browse
 */
class BrowseSearchPlaceholderUpdateTest extends KernelTestBase {

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

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_browse');
    require_once $module_path . '/mukurtu_browse.install';
  }

  /**
   * Fixture config for a view with the search_api_fulltext exposed filter.
   */
  protected function fulltextFilterFixture(string $placeholder): array {
    return [
      'display' => [
        'default' => [
          'display_options' => [
            'filters' => [
              'search_api_fulltext' => [
                'expose' => [
                  'placeholder' => $placeholder,
                ],
              ],
            ],
          ],
        ],
      ],
    ];
  }

  /**
   * The hook sets a distinct placeholder on both the Browse and Digital
   * Heritage views.
   */
  public function testUpdateSetsPlaceholderOnBothViews(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse')
      ->setData($this->fulltextFilterFixture('Enter keywords'))
      ->save();
    \Drupal::configFactory()->getEditable('views.view.mukurtu_digital_heritage_browse')
      ->setData($this->fulltextFilterFixture('Enter keywords'))
      ->save();

    mukurtu_browse_update_40019();

    $this->assertSame(
      'Search all content',
      \Drupal::config('views.view.mukurtu_browse')
        ->get('display.default.display_options.filters.search_api_fulltext.expose.placeholder')
    );
    $this->assertSame(
      'Search digital heritage items',
      \Drupal::config('views.view.mukurtu_digital_heritage_browse')
        ->get('display.default.display_options.filters.search_api_fulltext.expose.placeholder')
    );
  }

  /**
   * The update hook skips a view that doesn't exist on the site.
   */
  public function testUpdateSkipsMissingView(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_browse')
      ->setData($this->fulltextFilterFixture('Enter keywords'))
      ->save();

    $this->assertTrue(\Drupal::config('views.view.mukurtu_digital_heritage_browse')->isNew());

    mukurtu_browse_update_40019();

    $this->assertTrue(\Drupal::config('views.view.mukurtu_digital_heritage_browse')->isNew());
    $this->assertSame(
      'Search all content',
      \Drupal::config('views.view.mukurtu_browse')
        ->get('display.default.display_options.filters.search_api_fulltext.expose.placeholder')
    );
  }

}
