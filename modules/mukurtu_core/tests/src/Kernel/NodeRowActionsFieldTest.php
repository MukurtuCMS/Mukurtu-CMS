<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Plugin\views\field\NodeRowActionsField;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\ResultRow;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that NodeRowActionsField lists real moderation transitions for
 * moderated bundles, and falls back to plain publish/unpublish links for
 * non-moderated bundles.
 */
#[Group('mukurtu_core')]
class NodeRowActionsFieldTest extends KernelTestBase {

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
    'mukurtu_protocol',
    'mukurtu_workflows',
    'node',
    'og',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'workflows',
  ];

  /**
   * A user who owns/can edit test content.
   */
  protected User $owner;

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
    $this->installEntitySchema('og_membership');
    $this->installMukurtuWorkflowsConfig();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'landing_page', 'name' => 'Landing Page'])->save();

    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings)->save();

    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('access content');
    $role->grantPermission('edit any article content');
    $role->grantPermission('edit any landing_page content');
    $role->grantPermission('delete any article content');
    $role->grantPermission('delete any landing_page content');
    $role->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $role->grantPermission('use mukurtu_default_content_workflow transition publish');
    $role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $role->grantPermission('use mukurtu_default_content_workflow transition restore');
    $role->save();

    User::create(['name' => $this->randomMachineName()])->save();

    $this->owner = User::create(['name' => $this->randomMachineName()]);
    $this->owner->save();
    $this->container->get('current_user')->setAccount($this->owner);
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
   * Builds the field plugin with field_alias pointed at 'nid', bypassing a
   * full Views execution -- FieldPluginBase::getValue() only needs
   * $this->field_alias set to look up $values->{alias}.
   */
  protected function createField(): NodeRowActionsField {
    $field = NodeRowActionsField::create($this->container, [], 'mukurtu_node_row_actions', []);
    $field->field_alias = 'nid';
    return $field;
  }

  /**
   * A moderated node's row actions match its real valid transitions, with
   * no publish/unpublish keys.
   */
  public function testRowActionsForModeratedNode(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    $field = $this->createField();
    $build = $field->render(new ResultRow(['nid' => $node->id()]));

    $this->assertIsArray($build);
    $keys = array_keys($build['#links']);
    $this->assertContains('transition_draft', $keys);
    $this->assertContains('transition_archived', $keys);
    $this->assertNotContains('publish', $keys);
    $this->assertNotContains('unpublish', $keys);
  }

  /**
   * A non-moderated node keeps the plain publish/unpublish links.
   */
  public function testRowActionsForNonModeratedNode(): void {
    $node = Node::create([
      'type' => 'landing_page',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'status' => FALSE,
    ]);
    $node->save();

    $field = $this->createField();
    $build = $field->render(new ResultRow(['nid' => $node->id()]));

    $this->assertIsArray($build);
    $keys = array_keys($build['#links']);
    $this->assertContains('publish', $keys);
    $this->assertNotContains('unpublish', $keys);
  }

}
