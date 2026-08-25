<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40107(), which switches the content admin
 * view's bulk-form field to a plugin that filters options by real access.
 *
 * @see mukurtu_core_update_40107()
 */
#[Group('mukurtu_core')]
class MukurtuCoreUpdate40107Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
  ];

  /**
   * The hook re-imports the real shipped view config, not a fixture.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';

    \Drupal::configFactory()->getEditable('views.view.mukurtu_manage_all_content')
      ->setData([
        'id' => 'mukurtu_manage_all_content',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_options' => [
              'fields' => [
                'views_bulk_operations_bulk_form' => [
                  'id' => 'views_bulk_operations_bulk_form',
                  'plugin_id' => 'views_bulk_operations_bulk_form',
                ],
              ],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The update hook flips the field's plugin_id.
   */
  public function testUpdateSwitchesPluginId(): void {
    mukurtu_core_update_40107();

    $plugin_id = \Drupal::config('views.view.mukurtu_manage_all_content')
      ->get('display.default.display_options.fields.views_bulk_operations_bulk_form.plugin_id');

    $this->assertSame('mukurtu_access_filtered_bulk_form', $plugin_id);
  }

  /**
   * The update hook re-syncs the view to match the shipped config exactly.
   */
  public function testUpdateMatchesShippedViewConfig(): void {
    mukurtu_core_update_40107();

    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $shipped_config = (new FileStorage($profile_path . '/config/install'))
      ->read('views.view.mukurtu_manage_all_content');

    $active_plugin_id = \Drupal::config('views.view.mukurtu_manage_all_content')
      ->get('display.default.display_options.fields.views_bulk_operations_bulk_form.plugin_id');
    $shipped_plugin_id = $shipped_config['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['plugin_id'];

    $this->assertSame($shipped_plugin_id, $active_plugin_id);
  }

}
