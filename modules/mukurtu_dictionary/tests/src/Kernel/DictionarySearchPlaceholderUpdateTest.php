<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_dictionary_update_40043(), which gives the Dictionary search
 * box placeholder text instead of leaving it blank (#2018).
 *
 * @see mukurtu_dictionary_update_40043()
 */
#[Group('mukurtu_dictionary')]
class DictionarySearchPlaceholderUpdateTest extends KernelTestBase {

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

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_dictionary');
    require_once $module_path . '/mukurtu_dictionary.install';
  }

  /**
   * The hook sets the placeholder on the Dictionary view.
   */
  public function testUpdateSetsPlaceholder(): void {
    \Drupal::configFactory()->getEditable('views.view.mukurtu_dictionary')
      ->setData([
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
      ])
      ->save();

    mukurtu_dictionary_update_40043();

    $this->assertSame(
      'Search dictionary entries',
      \Drupal::config('views.view.mukurtu_dictionary')
        ->get('display.default.display_options.filters.search_api_fulltext.expose.placeholder')
    );
  }

  /**
   * The update hook is a no-op on a site that doesn't have this view.
   */
  public function testUpdateSkipsMissingView(): void {
    $this->assertTrue(\Drupal::config('views.view.mukurtu_dictionary')->isNew());

    mukurtu_dictionary_update_40043();

    $this->assertTrue(\Drupal::config('views.view.mukurtu_dictionary')->isNew());
  }

}
