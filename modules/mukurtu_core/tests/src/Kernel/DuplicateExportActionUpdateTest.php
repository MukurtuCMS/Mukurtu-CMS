<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_update_40102(), which removes the duplicate
 * "Add to export list" bulk action entry from the manage-all-content view.
 *
 * @see mukurtu_core_update_40102()
 */
#[Group('mukurtu_core')]
class DuplicateExportActionUpdateTest extends KernelTestBase {

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
                  'selected_actions' => [
                    4 => ['action_id' => 'mukurtu_export_add_to_list_action'],
                    5 => ['action_id' => 'mukurtu_export_remove_from_list_action'],
                    40 => ['action_id' => 'mukurtu_export_add_to_list_action'],
                    41 => ['action_id' => 'views_bulk_operations_delete_entity'],
                  ],
                ],
              ],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * The update hook removes the duplicate "Add to export list" entry.
   */
  public function testUpdateRemovesDuplicateExportListAction(): void {
    mukurtu_core_update_40102();

    $selected_actions = \Drupal::config('views.view.mukurtu_manage_all_content')
      ->get('display.default.display_options.fields.views_bulk_operations_bulk_form.selected_actions');

    $export_add_entries = array_filter($selected_actions, function (array $action): bool {
      return $action['action_id'] === 'mukurtu_export_add_to_list_action';
    });

    $this->assertCount(1, $export_add_entries, 'Expected exactly one "Add to export list" bulk action entry after update.');
  }

  /**
   * The update hook re-syncs the view to match the shipped config exactly.
   */
  public function testUpdateMatchesShippedViewConfig(): void {
    mukurtu_core_update_40102();

    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    $shipped_config = (new FileStorage($profile_path . '/config/install'))
      ->read('views.view.mukurtu_manage_all_content');

    $active_selected_actions = \Drupal::config('views.view.mukurtu_manage_all_content')
      ->get('display.default.display_options.fields.views_bulk_operations_bulk_form.selected_actions');
    $shipped_selected_actions = $shipped_config['display']['default']['display_options']['fields']['views_bulk_operations_bulk_form']['selected_actions'];

    $this->assertSame($shipped_selected_actions, $active_selected_actions);
  }

}
