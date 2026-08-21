<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Controller\NodeQuickActionsController;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\User;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests NodeQuickActionsController's moderation-aware behavior: the
 * transition() method applies/rejects state changes correctly, and the
 * legacy publish()/unpublish() methods now refuse to act on moderated
 * content instead of silently desyncing the published flag.
 */
#[Group('mukurtu_core')]
class NodeQuickActionsControllerTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'image',
    'media',
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
   * A user who owns test content and can update it.
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
    $this->installMinimalManageContentViewRoute();

    // article is structural and moderated; landing_page is deliberately
    // never attached to any workflow, matching this session's earlier
    // redesign, so it stands in for a non-moderated bundle.
    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();
    NodeType::create(['type' => 'landing_page', 'name' => 'Landing Page'])->save();

    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings)->save();

    $role = \Drupal\user\Entity\Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('access content');
    $role->grantPermission('edit any article content');
    $role->grantPermission('edit any landing_page content');
    $role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $role->save();

    // Burn uid 1 so it doesn't land on the owner below and mask denials.
    User::create(['name' => $this->randomMachineName()])->save();

    $this->owner = User::create(['name' => $this->randomMachineName()]);
    $this->owner->save();
    $this->container->get('current_user')->setAccount($this->owner);
  }

  /**
   * Registers a minimal stub view so the controller's redirect target
   * route (view.mukurtu_manage_all_content.mukurtu_manage_content) exists,
   * without needing the real view's plugins (mukurtu_core's row-actions
   * field, the new bulk action, etc.) or their providing modules enabled.
   */
  protected function installMinimalManageContentViewRoute(): void {
    \Drupal\views\Entity\View::create([
      'id' => 'mukurtu_manage_all_content',
      'label' => 'Mukurtu Manage All Content',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [],
        ],
        'mukurtu_manage_content' => [
          'display_plugin' => 'page',
          'id' => 'mukurtu_manage_content',
          'display_title' => 'Page',
          'position' => 1,
          'display_options' => [
            'path' => 'admin/content-test-stub',
          ],
        ],
      ],
    ])->save();
    \Drupal::service('router.builder')->rebuild();
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
   * Creates a moderated article, owned by $this->owner.
   */
  protected function createModeratedArticle(string $moderation_state): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'moderation_state' => $moderation_state,
    ]);
    $node->save();
    return $node;
  }

  /**
   * transition() rejects a $to_state not among the node's valid transitions.
   */
  public function testTransitionRejectsInvalidTransition(): void {
    $node = $this->createModeratedArticle('published');
    $original_revision_id = $node->getRevisionId();

    $controller = NodeQuickActionsController::create($this->container);
    $controller->transition($node, 'nonexistent_state', new Request());

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertEquals($original_revision_id, $node->getRevisionId());
  }

  /**
   * transition() applies a valid transition, updating state and revision.
   */
  public function testTransitionAppliesValidTransition(): void {
    $node = $this->createModeratedArticle('published');
    $original_revision_id = $node->getRevisionId();

    $controller = NodeQuickActionsController::create($this->container);
    $controller->transition($node, 'archived', new Request());

    $node = Node::load($node->id());
    $this->assertEquals('archived', $node->moderation_state->value);
    $this->assertNotEquals($original_revision_id, $node->getRevisionId());
  }

  /**
   * publish() refuses to act on a moderated node.
   */
  public function testPublishRefusesModeratedNode(): void {
    $node = $this->createModeratedArticle('draft');
    $this->assertFalse($node->isPublished());

    $controller = NodeQuickActionsController::create($this->container);
    $controller->publish($node, new Request());

    $node = Node::load($node->id());
    $this->assertFalse($node->isPublished());
    $this->assertEquals('draft', $node->moderation_state->value);
  }

  /**
   * unpublish() refuses to act on a moderated node.
   */
  public function testUnpublishRefusesModeratedNode(): void {
    $node = $this->createModeratedArticle('published');
    $this->assertTrue($node->isPublished());

    $controller = NodeQuickActionsController::create($this->container);
    $controller->unpublish($node, new Request());

    $node = Node::load($node->id());
    $this->assertTrue($node->isPublished());
    $this->assertEquals('published', $node->moderation_state->value);
  }

  /**
   * publish()/unpublish() still work on a non-moderated bundle.
   */
  public function testPublishStillWorksForNonModeratedNode(): void {
    $node = Node::create([
      'type' => 'landing_page',
      'title' => $this->randomString(),
      'uid' => $this->owner->id(),
      'status' => FALSE,
    ]);
    $node->save();
    $this->assertFalse($node->isPublished());

    $controller = NodeQuickActionsController::create($this->container);
    $controller->publish($node, new Request());

    $node = Node::load($node->id());
    $this->assertTrue($node->isPublished());
  }

}
