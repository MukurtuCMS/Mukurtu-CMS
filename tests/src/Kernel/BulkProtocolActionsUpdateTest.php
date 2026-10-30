<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40038().
 *
 * Existing sites get the cultural protocol actions on the bulk edit page.
 */
#[Group('mukurtu')]
class BulkProtocolActionsUpdateTest extends KernelTestBase {

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
  protected static $modules = ['system'];

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
   * Returns the bulk edit view's action IDs.
   */
  protected function actionIds(): array {
    return array_column(\Drupal::config('views.view.mukurtu_bulk_edit_content')->get(static::PATH), 'action_id');
  }

  /**
   * Missing actions are added after the site's own; a rerun does nothing.
   */
  public function testAddsMissingActions(): void {
    $this->writeView([
      ['action_id' => 'views_bulk_edit', 'preconfiguration' => ['add_confirmation' => TRUE]],
      ['action_id' => 'mukurtu_remove_protocol_action', 'preconfiguration' => ['label_override' => 'Site label']],
    ]);

    $this->assertSame('Added the cultural protocol actions to the bulk edit page.', mukurtu_update_40038());
    $this->assertSame([
      'views_bulk_edit',
      'mukurtu_remove_protocol_action',
      'mukurtu_add_protocol_action',
      'mukurtu_change_sharing_action',
    ], $this->actionIds());
    $actions = \Drupal::config('views.view.mukurtu_bulk_edit_content')->get(static::PATH);
    $this->assertSame(['label_override' => 'Site label'], $actions[1]['preconfiguration']);

    $this->assertStringContainsString('nothing to change', mukurtu_update_40038());
  }

  /**
   * A site without the bulk edit page is left alone.
   */
  public function testNoPage(): void {
    $this->assertSame("There's no bulk edit page; nothing to change.", mukurtu_update_40038());
    $this->assertTrue(\Drupal::config('views.view.mukurtu_bulk_edit_content')->isNew());
  }

}
