<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40041().
 *
 * Existing sites get one-by-one editing on both bulk edit pages.
 */
#[Group('mukurtu')]
class BulkEditGridActionUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   *
   * The views are written raw, as a real site stores them, without the
   * modules whose plugins they name. Only selected_actions matters here.
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * The selected_actions path on the bulk edit views.
   */
  protected const PATH = 'display.default.display_options.fields.views_bulk_operations_bulk_form.selected_actions';

  /**
   * The bulk edit views.
   */
  protected const VIEWS = ['mukurtu_bulk_edit_content', 'mukurtu_bulk_edit_media'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    require_once $this->root . '/' . \Drupal::service('extension.list.profile')->getPath('mukurtu') . '/mukurtu.install';
  }

  /**
   * Writes a bulk edit view with the given actions.
   */
  protected function writeView(string $view, array $actions): void {
    \Drupal::service('config.storage')->write("views.view.$view", [
      'id' => $view,
      'display' => ['default' => ['display_options' => ['fields' => ['views_bulk_operations_bulk_form' => ['selected_actions' => $actions]]]]],
    ]);
    \Drupal::configFactory()->reset("views.view.$view");
  }

  /**
   * The action is added to both pages once, keeping site changes.
   */
  public function testAddsActionOnce(): void {
    foreach (self::VIEWS as $view) {
      $this->writeView($view, [
        ['action_id' => 'views_bulk_edit', 'preconfiguration' => ['label_override' => 'Site label']],
      ]);
    }

    $this->assertSame('Added one-by-one editing to the bulk edit pages.', mukurtu_update_40041());
    foreach (self::VIEWS as $view) {
      $actions = \Drupal::config("views.view.$view")->get(self::PATH);
      $this->assertSame(['views_bulk_edit', 'mukurtu_edit_items_grid_action'], array_column($actions, 'action_id'));
      $this->assertSame('Site label', $actions[0]['preconfiguration']['label_override']);
    }
    $this->assertSame('The bulk edit pages already have one-by-one editing; nothing to change.', mukurtu_update_40041());
  }

  /**
   * A site without the media page still gets the action on content.
   */
  public function testWithoutMediaPage(): void {
    $this->writeView('mukurtu_bulk_edit_content', []);

    $this->assertSame('Added one-by-one editing to the bulk edit pages.', mukurtu_update_40041());
    $this->assertSame(['mukurtu_edit_items_grid_action'], array_column(\Drupal::config('views.view.mukurtu_bulk_edit_content')->get(self::PATH), 'action_id'));
    $this->assertTrue(\Drupal::config('views.view.mukurtu_bulk_edit_media')->isNew());
  }

}
