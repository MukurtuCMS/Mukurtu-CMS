<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\mukurtu_import\Form\BulkEditGridForm;
use Drupal\mukurtu_import\Plugin\Action\MukurtuEditItemsGridAction;
use Drupal\node\Entity\Node;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the per-item grid's entry checks, action access and export hook.
 */
#[Group('mukurtu_import')]
class BulkEditGridFormTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations'];

  /**
   * Saves a VBO selection on the content bulk edit page.
   */
  protected function select(array $data): void {
    $this->container->get('tempstore.private')
      ->get('views_bulk_operations_mukurtu_bulk_edit_content_page')
      ->set((string) $this->currentUser->id(), $data + [
        'view_id' => 'mukurtu_bulk_edit_content',
        'display_id' => 'page',
        'action_id' => 'mukurtu_edit_items_grid_action',
        'exclude_mode' => FALSE,
      ]);
  }

  /**
   * Builds the grid for the content bulk edit page.
   */
  protected function grid(): array {
    return $this->container->get('form_builder')->getForm(BulkEditGridForm::class, 'mukurtu_bulk_edit_content', 'page');
  }

  /**
   * Without a selection, the grid explains instead of failing.
   */
  public function testNoSelection(): void {
    $form = $this->grid();
    $this->assertSame('This action has already run, or your selection has expired. Nothing was changed.', (string) $form['mukurtu_stale_selection']['#value']);
    $this->assertArrayNotHasKey('grid', $form);
  }

  /**
   * More than 50 items asks the editor to narrow the selection.
   */
  public function testTooMany(): void {
    $list = [];
    for ($id = 1; $id <= 51; $id++) {
      $list["key$id"] = [$id, 'en', 'node', $id];
    }
    $this->select(['list' => $list]);

    $form = $this->grid();
    $this->assertSame('You selected 51 items. Edit up to 50 at a time: narrow the selection and try again.', (string) $form['too_many']['#value']);
    $this->assertArrayNotHasKey('grid', $form);

    // With every result selected, VBO lists the excluded rows instead.
    $this->select(['list' => [], 'exclude_mode' => TRUE, 'total_results' => 60]);
    $this->assertStringStartsWith('You selected 60 items.', (string) $this->grid()['too_many']['#value']);
  }

  /**
   * The action needs "Use bulk editing" and edit access to each item.
   */
  public function testActionAccess(): void {
    $node = Node::create(['type' => 'protocol_aware_content', 'title' => 'Canoe', 'uid' => $this->currentUser->id()]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $action = $this->container->get('plugin.manager.views_bulk_operations_action')->createInstance('mukurtu_edit_items_grid_action');
    $this->assertInstanceOf(MukurtuEditItemsGridAction::class, $action);

    $without = $this->createUser([], ['access content']);
    $this->assertFalse($action->access($node, $without));
    $this->assertTrue($action->access($node, $this->currentUser));
  }

  /**
   * Mukurtu Export is turned on if a site has it off.
   */
  public function testEnableExportUpdate(): void {
    require_once $this->root . '/' . \Drupal::service('extension.list.module')->getPath('mukurtu_import') . '/mukurtu_import.install';
    $installer = $this->createMock(ModuleInstallerInterface::class);
    $installer->expects($this->once())->method('install')->with(['mukurtu_export']);
    $this->container->set('module_installer', $installer);

    $this->assertFalse(\Drupal::moduleHandler()->moduleExists('mukurtu_export'));
    $this->assertSame('Turned on Mukurtu Export.', mukurtu_import_update_40405());
  }

}
