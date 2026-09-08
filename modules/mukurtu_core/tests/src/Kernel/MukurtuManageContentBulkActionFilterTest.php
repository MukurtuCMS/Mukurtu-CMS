<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Config\FileStorage;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\content_moderation\Traits\ContentModerationTestTrait;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use Drupal\views\Entity\View;
use Drupal\views\Form\ViewsForm;
use Drupal\views\Views;
use Drupal\workflows\Entity\Workflow;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the mukurtu_manage_all_content bulk-action dropdown only
 * lists actions the current viewer can actually perform.
 *
 * Deliberately exercises the *real* Views form-building path
 * (DisplayPluginBase::render() -> ViewsForm::create() ->
 * FormBuilder::getForm() -> hook_form_alter()) rather than a
 * hand-constructed stub. An earlier field-plugin-based implementation of
 * this filter passed a synthetic-stub Kernel test but silently never
 * executed during real Views rendering -- a genuinely different code
 * path -- so this test is built specifically to not repeat that gap.
 */
#[Group('mukurtu_core')]
class MukurtuManageContentBulkActionFilterTest extends KernelTestBase {

  use ContentModerationTestTrait;

  /**
   * This test builds a minimal but real view config fixture directly,
   * not from the profile's shipped install config.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

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
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_map');
    $this->installSchema('mukurtu_protocol', 'mukurtu_protocol_access');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installEntitySchema('content_moderation_state');
    $this->installEntitySchema('community');
    $this->installEntitySchema('protocol');
    $this->installEntitySchema('og_membership');
    $this->installMukurtuWorkflowsConfig();

    // mukurtu_protocol's node-grants cache context queries OG membership
    // tables on every access check once the module is enabled, even for
    // a plain non-protocol node.
    node_access_rebuild();

    NodeType::create(['type' => 'article', 'name' => 'Article'])->save();

    $workflow = Workflow::load('mukurtu_default_content_workflow');
    $type_settings = $workflow->get('type_settings');
    $type_settings['entity_types']['node'] = ['article'];
    $workflow->set('type_settings', $type_settings)->save();

    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('access content');
    $role->save();

    User::create(['name' => $this->randomMachineName()])->save();

    // A minimal but real view: the id must be 'mukurtu_manage_all_content'
    // to match the hook's form_id check, with a real VBO bulk-form field
    // configured with one moderation-transition action, matching the
    // shipped view's shape closely enough to exercise the same mechanism.
    View::create([
      'id' => 'mukurtu_manage_all_content',
      'label' => 'Mukurtu Manage All Content',
      'base_table' => 'node_field_data',
      'display' => [
        'default' => [
          'display_plugin' => 'default',
          'id' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'fields' => [
              'views_bulk_operations_bulk_form' => [
                'id' => 'views_bulk_operations_bulk_form',
                'table' => 'views',
                'field' => 'views_bulk_operations_bulk_form',
                'relationship' => 'none',
                'plugin_id' => 'views_bulk_operations_bulk_form',
                'selected_actions' => [
                  1 => [
                    'action_id' => 'mukurtu_change_moderation_state_action',
                    'preconfiguration' => ['target_state' => 'archived'],
                  ],
                ],
              ],
            ],
          ],
        ],
        'page_1' => [
          'display_plugin' => 'page',
          'id' => 'page_1',
          'display_title' => 'Page',
          'position' => 1,
          'display_options' => [
            'path' => 'admin/content-test',
          ],
        ],
      ],
    ])->save();

    // VBO's bulk form resolves a redirect URL from the current request
    // (Url::createFromRequest()), which needs a real, routable request on
    // the stack -- the router's default fallback request has no matching
    // route.
    \Drupal::service('router.builder')->rebuild();
    $request = \Symfony\Component\HttpFoundation\Request::create('/admin/content-test');
    $request->setSession($this->container->get('session'));
    $this->container->get('request_stack')->push($request);
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
   * Renders the real bulk-action form for the given user and returns the
   * rendered #options array, via the exact mechanism Drupal core uses in
   * DisplayPluginBase::render() -- not a hand-built form array.
   */
  protected function renderBulkActionOptions(User $account): array {
    $this->container->get('current_user')->setAccount($account);

    $view = Views::getView('mukurtu_manage_all_content');
    $view->setDisplay('page_1');
    $view->execute();

    $form_object = ViewsForm::create($this->container, $view->storage->id(), $view->current_display, $view->args);
    $build = $this->container->get('form_builder')->getForm($form_object, $view, []);

    return $build['header']['views_bulk_operations_bulk_form']['action']['#options'] ?? [];
  }

  /**
   * A user who can't archive anything visible sees no bulk actions at all.
   */
  public function testUnprivilegedUserSeesNoActions(): void {
    $owner = User::create(['name' => $this->randomMachineName()]);
    $owner->save();

    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $owner->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    $viewer = User::create(['name' => $this->randomMachineName()]);
    $viewer->save();

    $options = $this->renderBulkActionOptions($viewer);
    $this->assertArrayNotHasKey(1, $options, 'Archive option is absent for a user who cannot archive anything visible.');
  }

  /**
   * A user who can archive the visible node sees the Archive option.
   */
  public function testPrivilegedUserSeesArchiveAction(): void {
    $manager_role = Role::create(['id' => 'manager', 'label' => 'Manager']);
    $manager_role->grantPermission('access content');
    $manager_role->grantPermission('edit any article content');
    $manager_role->grantPermission('use mukurtu_default_content_workflow transition archive');
    $manager_role->save();
    $manager = User::create(['name' => $this->randomMachineName(), 'roles' => ['manager']]);
    $manager->save();

    $node = Node::create([
      'type' => 'article',
      'title' => $this->randomString(),
      'uid' => $manager->id(),
      'moderation_state' => 'published',
    ]);
    $node->save();

    $options = $this->renderBulkActionOptions($manager);
    $this->assertArrayHasKey(1, $options, 'Archive option is present for a user who can archive the visible node.');
  }

}
