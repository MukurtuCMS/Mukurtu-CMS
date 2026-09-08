<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\mukurtu_export\Form\ExportListAddItemsForm;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the "Add to export list" VBO bulk action's route and form.
 *
 * Regression test for issue #2058: the bulk action's confirm_form_route_name
 * ('mukurtu_export.add_items_to_list') was never defined in
 * mukurtu_export.routing.yml, so choosing "Add to export list" from
 * /admin/content threw a RouteNotFoundException.
 */
#[Group('mukurtu_export')]
class ExportListAddItemsFormTest extends ProtocolAwareEntityTestBase {

  protected static $modules = [
    'mukurtu_export',
    'mukurtu_multipage_items',
    'views_bulk_operations',
    'field',
  ];

  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('export_list');
    $this->installEntitySchema('multipage_item');

    if (!NodeType::load('collection')) {
      NodeType::create(['type' => 'collection', 'name' => 'Collection'])->save();
    }
  }

  /**
   * The route the VBO action plugins redirect to must actually exist.
   */
  public function testAddItemsToListRouteExists(): void {
    $route = \Drupal::service('router.route_provider')->getRouteByName('mukurtu_export.add_items_to_list');

    $this->assertSame('Drupal\mukurtu_export\Form\ExportListAddItemsForm', $route->getDefault('_form'));
    $this->assertSame('access mukurtu export', $route->getRequirement('_permission'));
  }

  /**
   * Submitting the form adds the VBO-selected entities to the chosen list.
   */
  public function testSubmitFormAddsSelectedItemsToExportList(): void {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
    ]);
    $node->save();

    $list = \Drupal::entityTypeManager()->getStorage('export_list')->create([
      'label' => $this->randomString(),
      'uid' => $this->currentUser->id(),
      'site_wide' => FALSE,
    ]);
    $list->save();

    $form_data = [
      'view_id' => 'content',
      'display_id' => 'page_1',
      'action_id' => 'mukurtu_export_add_to_list_action',
      'action_label' => 'Add to export list',
      'list' => [
        [$node->id(), 'en', 'node', $node->id()],
      ],
      'exclude_mode' => FALSE,
      'redirect_url' => Url::fromRoute('<front>'),
    ];

    $form_state = new FormState();
    $form_state->set('views_bulk_operations', $form_data);
    $form_state->setValues([
      'export_list_id' => $list->id(),
      'new_list_name' => '',
    ]);

    $form = [];
    $form_object = ExportListAddItemsForm::create($this->container);
    $form_object->submitForm($form, $form_state);

    $reloaded = \Drupal::entityTypeManager()->getStorage('export_list')->loadUnchanged($list->id());
    $items = $reloaded->getItems();

    $this->assertArrayHasKey('node', $items);
    $this->assertArrayHasKey($node->id(), $items['node']);
  }

  /**
   * Submitting with a new list name creates the list and adds items to it.
   */
  public function testSubmitFormCreatesNewListWhenNameProvided(): void {
    $node = Node::create([
      'type' => 'protocol_aware_content',
      'title' => $this->randomString(),
      'uid' => $this->currentUser->id(),
    ]);
    $node->save();

    $new_list_name = $this->randomString();
    $form_data = [
      'view_id' => 'content',
      'display_id' => 'page_1',
      'action_id' => 'mukurtu_export_add_to_list_action',
      'action_label' => 'Add to export list',
      'list' => [
        [$node->id(), 'en', 'node', $node->id()],
      ],
      'exclude_mode' => FALSE,
      'redirect_url' => Url::fromRoute('<front>'),
    ];

    $form_state = new FormState();
    $form_state->set('views_bulk_operations', $form_data);
    $form_state->setValues([
      'export_list_id' => '',
      'new_list_name' => $new_list_name,
    ]);

    $form = [];
    $form_object = ExportListAddItemsForm::create($this->container);
    $form_object->submitForm($form, $form_state);

    $storage = \Drupal::entityTypeManager()->getStorage('export_list');
    $lists = $storage->loadByProperties(['label' => $new_list_name]);
    $list = reset($lists);

    $this->assertNotFalse($list);
    $this->assertArrayHasKey($node->id(), $list->getItems()['node'] ?? []);
  }

}
