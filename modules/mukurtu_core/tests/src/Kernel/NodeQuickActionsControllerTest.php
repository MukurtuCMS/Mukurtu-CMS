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
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    $this->installMukurtuWorkflowsConfig();
    $this->installMinimalManageContentViewRoute();

    // mukurtu_protocol's hook_node_grants()/hook_node_access_records()
    // are invoked for every 'view' access check once the module is
    // enabled, even for a plain non-protocol node -- ModerationTransitionAccessResolver
    // now checks 'view' for archive/restore, so this schema/rebuild is
    // needed even though this test has no protocol-gated content.
    node_access_rebuild();

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
      $data = $storage->read($name);
      if ($data === FALSE) {
        throw new \RuntimeException(sprintf(
          'Could not read config "%s" from %s -- confirm this local checkout of mukurtu_workflows is up to date with the branch under test.',
          $name,
          $storage->getFilePath($name),
        ));
      }
      \Drupal::configFactory()->getEditable($name)->setData($data)->save();
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
   * A user with only 'view' access (no update) but holding the archive
   * transition permission can still archive via the controller -- the
   * route only requires 'view' now, and the controller's own
   * re-validation uses the same view-based carve-out for archive/restore.
   */
  public function testTransitionAppliesArchiveForViewOnlyUser(): void {
    // The shared setUp() grants 'edit any article content' to
    // 'authenticated' broadly (every user, including the viewer below,
    // gets that role) -- revoke it here so this test's "view only, no
    // update" premise actually holds.
    $authenticated = \Drupal\user\Entity\Role::load('authenticated');
    $authenticated->revokePermission('edit any article content');
    $authenticated->save();

    $viewer_role = \Drupal\user\Entity\Role::create(['id' => 'viewer', 'label' => 'Viewer']);
    $viewer_role->grantPermission('access content');
    $viewer_role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $viewer_role->save();
    $viewer = User::create(['name' => $this->randomMachineName(), 'roles' => ['viewer']]);
    $viewer->save();

    $node = $this->createModeratedArticle('published');
    $this->container->get('current_user')->setAccount($viewer);

    $controller = NodeQuickActionsController::create($this->container);
    $controller->transition($node, 'archived', new Request());

    $node = Node::load($node->id());
    $this->assertEquals('archived', $node->moderation_state->value);
  }

  /**
   * The same view-only user is rejected for a non-archive/restore
   * transition, which still requires real 'update' access.
   */
  public function testTransitionRejectsNonArchiveTransitionForViewOnlyUser(): void {
    // See testTransitionAppliesArchiveForViewOnlyUser() -- same reason.
    $authenticated = \Drupal\user\Entity\Role::load('authenticated');
    $authenticated->revokePermission('edit any article content');
    $authenticated->save();

    $viewer_role = \Drupal\user\Entity\Role::create(['id' => 'viewer', 'label' => 'Viewer']);
    $viewer_role->grantPermission('access content');
    $viewer_role->grantPermission('use mukurtu_default_content_workflow transition create_new_draft');
    $viewer_role->save();
    $viewer = User::create(['name' => $this->randomMachineName(), 'roles' => ['viewer']]);
    $viewer->save();

    $node = $this->createModeratedArticle('published');
    $original_revision_id = $node->getRevisionId();
    $this->container->get('current_user')->setAccount($viewer);

    $controller = NodeQuickActionsController::create($this->container);
    $controller->transition($node, 'draft', new Request());

    $node = Node::load($node->id());
    $this->assertEquals('published', $node->moderation_state->value);
    $this->assertEquals($original_revision_id, $node->getRevisionId());
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
