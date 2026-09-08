<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_core_preprocess_views_view(), which adds an "All content"
 * link to the end of the dashboard's "Recent content" block by building the
 * link directly against the mukurtu_manage_all_content view's named route,
 * rather than through Views' own more-link (which resolves through raw path
 * matching and doesn't reliably reach that route on this site - see
 * RouteSubscriber::alterRoutes()).
 *
 * This uses a minimal fixture for views.view.mukurtu_manage_all_content
 * (same view/display ID as the real one, so the generated route name
 * matches, but with a plain 'perm' access plugin) rather than the real
 * shipped view, to avoid pulling in mukurtu_protocol's full dependency
 * chain (og, media, layout_builder, ...) just to exercise this hook's own
 * branching logic.
 *
 * @see mukurtu_core_preprocess_views_view()
 */
#[Group('mukurtu_core')]
class RecentContentMoreLinkPreprocessTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installConfig(['user']);

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.module';

    \Drupal::configFactory()->getEditable('views.view.mukurtu_manage_all_content')
      ->setData([
        'status' => TRUE,
        'id' => 'mukurtu_manage_all_content',
        'label' => 'Manage all content',
        'module' => 'views',
        'base_table' => 'users_field_data',
        'base_field' => 'uid',
        'display' => [
          'default' => [
            'id' => 'default',
            'display_title' => 'Default',
            'display_plugin' => 'default',
            'display_options' => [
              'access' => [
                'type' => 'perm',
                'options' => [
                  'perm' => 'access content overview',
                ],
              ],
            ],
          ],
          'mukurtu_manage_content' => [
            'id' => 'mukurtu_manage_content',
            'display_title' => 'Page',
            'display_plugin' => 'page',
            'display_options' => [
              'path' => 'all-content-test-fixture',
            ],
          ],
        ],
      ])
      ->save();

    \Drupal::service('router.builder')->rebuild();

    // The first user entity created in a Kernel test becomes uid 1, which
    // bypasses all permission checks. Create a throwaway account here so the
    // accounts each test method creates for its own assertions get uid > 1
    // and are actually subject to the permission checks being tested.
    User::create(['name' => 'uid-1-placeholder'])->save();
  }

  /**
   * Builds a stub "view" object exposing only what the hook reads.
   */
  protected function stubView(string $id, string $current_display): object {
    return new class($id, $current_display) {
      public $current_display;
      protected $id;

      public function __construct(string $id, string $current_display) {
        $this->id = $id;
        $this->current_display = $current_display;
      }

      public function id() {
        return $this->id;
      }

    };
  }

  /**
   * Adds the link, visible to a user with access to the destination route.
   */
  public function testAddsMoreLinkForContentRecentBlockDisplayWithAccess(): void {
    Role::create(['id' => 'access_role', 'label' => 'Access role'])
      ->grantPermission('access content overview')
      ->save();
    $account = User::create(['name' => 'has-access', 'roles' => ['access_role']]);
    $account->save();
    \Drupal::currentUser()->setAccount($account);

    $variables = ['view' => $this->stubView('content_recent', 'block_1')];
    mukurtu_core_preprocess_views_view($variables);

    $this->assertArrayHasKey('more', $variables);
    $this->assertEquals('more_link', $variables['more']['#type']);
    $this->assertEquals('All content', (string) $variables['more']['#title']);
    $this->assertEquals('view.mukurtu_manage_all_content.mukurtu_manage_content', $variables['more']['#url']->getRouteName());
    $this->assertTrue($variables['more']['#access']);
  }

  /**
   * Hides the link (#access = FALSE) for a user without route access.
   */
  public function testMoreLinkHiddenWithoutAccess(): void {
    $account = User::create(['name' => 'no-access']);
    $account->save();
    \Drupal::currentUser()->setAccount($account);

    $variables = ['view' => $this->stubView('content_recent', 'block_1')];
    mukurtu_core_preprocess_views_view($variables);

    $this->assertArrayHasKey('more', $variables);
    $this->assertFalse($variables['more']['#access']);
  }

  /**
   * Leaves other views/displays untouched.
   */
  public function testLeavesOtherViewsUntouched(): void {
    $variables = ['view' => $this->stubView('unrelated_view', 'block_1')];
    mukurtu_core_preprocess_views_view($variables);
    $this->assertArrayNotHasKey('more', $variables);

    $variables = ['view' => $this->stubView('content_recent', 'default')];
    mukurtu_core_preprocess_views_view($variables);
    $this->assertArrayNotHasKey('more', $variables);
  }

}
