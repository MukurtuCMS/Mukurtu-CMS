<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Plugin\views\field\MukurtuAccessFilteredBulkForm;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\ResultRow;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that MukurtuAccessFilteredBulkForm drops bulk-action options none
 * of the current page's rows are actually accessible for, instead of
 * listing every configured action unconditionally like VBO's own field.
 */
#[Group('mukurtu_core')]
class MukurtuAccessFilteredBulkFormTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_workflows',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installSchema('node', ['node_access']);
    $this->installSchema('system', ['sequences']);
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installMukurtuWorkflowsConfig();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings)->save();

    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('access content');
    $role->save();

    User::create(['name' => $this->randomMachineName()])->save();
  }

  /**
   * Installs only the workflow/settings config this test needs.
   */
  protected function installMukurtuWorkflowsConfig(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_workflows');
    $storage = new FileStorage($module_path . '/config/install');
    foreach ([
      'workflows.workflow.mukurtu_default_content_workflow',
      'workflows.workflow.mukurtu_editorial_workflow',
      'mukurtu_workflows.settings',
    ] as $name) {
      \Drupal::configFactory()->getEditable($name)->setData($storage->read($name))->save();
    }
  }

  /**
   * Builds the field plugin, wired to a stub view result and a single
   * "Archive" bulk-action option.
   *
   * @param \Drupal\node\Entity\Node[] $nodes
   *   The nodes to sample against (the current page's rows).
   */
  protected function createField(array $nodes): MukurtuAccessFilteredBulkForm {
    $field = MukurtuAccessFilteredBulkForm::create($this->container, [], 'mukurtu_access_filtered_bulk_form', []);

    $field->options = [
      'relationship' => 'none',
      'selected_actions' => [
        1 => [
          'action_id' => 'mukurtu_change_moderation_state_action',
          'preconfiguration' => ['target_state' => 'archived'],
        ],
      ],
    ];

    $reflection = new \ReflectionClass($field);
    $actions_property = $reflection->getProperty('actions');
    $actions_property->setAccessible(TRUE);
    $actions_property->setValue($field, [
      'mukurtu_change_moderation_state_action' => ['label' => 'Change moderation state', 'type' => 'node'],
    ]);

    $view = new \stdClass();
    $view->result = [];
    foreach ($nodes as $node) {
      $row = new ResultRow();
      $row->_entity = $node;
      $view->result[] = $row;
    }
    $field->view = $view;

    return $field;
  }

  /**
   * Calls the protected getBulkOptions() via reflection.
   */
  protected function getBulkOptions(MukurtuAccessFilteredBulkForm $field): array {
    $reflection = new \ReflectionClass($field);
    $method = $reflection->getMethod('getBulkOptions');
    $method->setAccessible(TRUE);
    return $method->invoke($field);
  }

  /**
   * The "Archive" option is offered when at least one visible row is
   * accessible for it.
   */
  public function testOptionPresentWhenAtLeastOneRowIsAccessible(): void {
    $manager_role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $manager_role->grantPermission('access content');
    $manager_role->grantPermission('edit any article content');
    $manager_role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $manager_role->save();
    $manager = User::create(['name' => $this->randomMachineName(), 'roles' => ['manager']]);
    $manager->save();
    $this->container->get('current_user')->setAccount($manager);

    $published = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $manager->id(),
      'moderation_state' => 'published',
    ]);
    $published->save();

    $field = $this->createField([$published]);
    $options = $this->getBulkOptions($field);

    $this->assertArrayHasKey(1, $options, 'Archive option is offered when the viewer can archive at least one visible row.');
  }

  /**
   * The "Archive" option is dropped when none of the visible rows are
   * accessible for it -- the bug this fix closes.
   */
  public function testOptionAbsentWhenNoRowIsAccessible(): void {
    $viewer_role = Role::create(['id' => 'viewer', 'label' => 'Viewer']);
    $viewer_role->grantPermission('access content');
    $viewer_role->save();
    $viewer = User::create(['name' => $this->randomMachineName(), 'roles' => ['viewer']]);
    $viewer->save();

    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();

    $published = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $owner->id(),
      'moderation_state' => 'published',
    ]);
    $published->save();

    $this->container->get('current_user')->setAccount($viewer);
    $field = $this->createField([$published]);
    $options = $this->getBulkOptions($field);

    $this->assertArrayNotHasKey(1, $options, 'Archive option is dropped when the viewer cannot perform it on any visible row.');
  }

}
