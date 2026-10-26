<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\Core\Entity\Entity\EntityFormMode;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40037().
 *
 * Existing sites get Views Bulk Edit, the bulk_edit form mode and the
 * "Modify field values" action on the bulk edit page.
 */
#[Group('mukurtu')]
class BulkFieldEditUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The view is written raw, as a real site stores it, without the modules
   * whose plugins it names. Only selected_actions matters here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
  ];

  /**
   * The selected_actions path on the bulk edit view.
   */
  protected const PATH = 'display.default.display_options.fields.views_bulk_operations_bulk_form.selected_actions';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Writes the bulk edit view with the given actions.
   */
  protected function writeView(array $actions): void {
    \Drupal::service('config.storage')->write('views.view.mukurtu_bulk_edit_content', [
      'id' => 'mukurtu_bulk_edit_content',
      'display' => ['default' => ['display_options' => ['fields' => ['views_bulk_operations_bulk_form' => ['selected_actions' => $actions]]]]],
    ]);
    \Drupal::configFactory()->reset('views.view.mukurtu_bulk_edit_content');
  }

  /**
   * The module, form mode and action are added; a second run changes nothing.
   */
  public function testSetsUpFieldEditing(): void {
    $this->writeView([['action_id' => 'mukurtu_add_to_collection_action']]);

    $message = mukurtu_update_40037();

    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('views_bulk_edit'));
    $this->assertNotNull(EntityFormMode::load('node.bulk_edit'));
    $this->assertSame([
      ['action_id' => 'mukurtu_add_to_collection_action'],
      ['action_id' => 'views_bulk_edit', 'preconfiguration' => ['add_confirmation' => TRUE]],
    ], \Drupal::config('views.view.mukurtu_bulk_edit_content')->get(static::PATH));
    $this->assertStringContainsString('Enabled Views Bulk Edit.', $message);

    $this->assertStringContainsString('nothing to change', mukurtu_update_40037());
  }

  /**
   * A site that already offers the action keeps its own settings for it.
   */
  public function testKeepsExistingAction(): void {
    $this->writeView([['action_id' => 'views_bulk_edit']]);

    mukurtu_update_40037();

    $this->assertSame([['action_id' => 'views_bulk_edit']], \Drupal::config('views.view.mukurtu_bulk_edit_content')->get(static::PATH));
  }

}
