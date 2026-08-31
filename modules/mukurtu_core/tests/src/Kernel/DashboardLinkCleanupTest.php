<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the dashboard link cleanup for issues #1787 and #2057:
 * - mukurtu_core_update_40107() orders Security before Site information.
 * - mukurtu_core_update_40108() splits Site settings into "Publication tools"
 *   and "Local Contexts" sections.
 * - mukurtu_core_update_40110() moves "Site-wide comment settings" into
 *   Publication tools and creates a "Notifications & Review" section for
 *   comment review, the review queue, pending submissions, and
 *   notifications.
 * - The Configure Community/Protocol Permissions links are gone.
 * - The Visitors "Analytics" / "Visitor settings" links are present.
 * - The Multilingual section no longer has a duplicate "Manage site languages".
 *
 * @see mukurtu_core_update_40107()
 * @see mukurtu_core_update_40108()
 * @see mukurtu_core_update_40110()
 * @see mukurtu_protocol_update_40044()
 */
#[Group('mukurtu_core')]
class DashboardLinkCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * The hook writes a partial dashboards config fixture, not full schema data.
   *
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The uuids the shipped dashboard config uses for the two reordered blocks.
   */
  protected const SECURITY_UUID = '3f8b6c1a-2e4d-4a9f-9c7b-5d1e8a2f6b3c';
  protected const SITE_INFO_UUID = '7ea8f4fe-f816-4b62-80ed-597ea6d8d39a';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * Saves a dashboard config fixture with the pre-#1787 Right-column weights.
   */
  protected function saveDashboardConfig(int $security_weight = 9, int $site_info_weight = 8): void {
    \Drupal::configFactory()->getEditable('dashboards.dashboard.mukurtu_dashboard')
      ->setData([
        'id' => 'mukurtu_dashboard',
        'sections' => [
          [
            'components' => [
              self::SECURITY_UUID => [
                'uuid' => self::SECURITY_UUID,
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-security'],
                'weight' => $security_weight,
              ],
              self::SITE_INFO_UUID => [
                'uuid' => self::SITE_INFO_UUID,
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-site-info'],
                'weight' => $site_info_weight,
              ],
            ],
          ],
        ],
      ])
      ->save();
  }

  /**
   * Reads a menu links YAML file for the given module.
   */
  protected function menuLinks(string $module): array {
    $path = \Drupal::service('extension.list.module')->getPath($module);
    return Yaml::parseFile(\Drupal::root() . "/$path/$module.links.menu.yml") ?? [];
  }

  /**
   * The render order of a dashboard menu, from every module's *.links.menu.yml.
   *
   * Mirrors how system_menu_block sorts: by weight, then by title. Dynamic
   * links added in hook_menu_links_discovered_alter() are not included.
   *
   * @return string[]
   *   Link titles in render order.
   */
  protected function dashboardMenuOrder(string $menu_name): array {
    $root = \Drupal::root();
    $items = [];
    foreach (glob($root . '/' . dirname(\Drupal::service('extension.list.module')->getPath('mukurtu_core'), 1) . '/*/*.links.menu.yml') as $file) {
      foreach (Yaml::parseFile($file) ?? [] as $link) {
        if (($link['menu_name'] ?? NULL) === $menu_name) {
          $items[] = [(int) ($link['weight'] ?? 0), (string) ($link['title'] ?? '')];
        }
      }
    }
    usort($items, static fn(array $a, array $b): int => [$a[0], mb_strtolower($a[1])] <=> [$b[0], mb_strtolower($b[1])]);
    return array_map(static fn(array $i): string => $i[1], $items);
  }

  /**
   * The update hook swaps the Security and Site information weights.
   */
  public function testUpdateReordersSecurityBeforeSiteInfo(): void {
    $this->saveDashboardConfig();

    mukurtu_core_update_40107();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $this->assertSame(8, $components[self::SECURITY_UUID]['weight']);
    $this->assertSame(9, $components[self::SITE_INFO_UUID]['weight']);
  }

  /**
   * Running the reorder twice leaves the weights untouched.
   */
  public function testReorderIsIdempotent(): void {
    $this->saveDashboardConfig(8, 9);

    mukurtu_core_update_40107();
    mukurtu_core_update_40107();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $this->assertSame(8, $components[self::SECURITY_UUID]['weight']);
    $this->assertSame(9, $components[self::SITE_INFO_UUID]['weight']);
  }

  /**
   * The update hook is a no-op when the dashboard config doesn't exist.
   */
  public function testUpdateIsNoOpWithoutDashboardConfig(): void {
    $this->assertTrue(\Drupal::config('dashboards.dashboard.mukurtu_dashboard')->isNew());

    mukurtu_core_update_40107();

    $this->assertTrue(\Drupal::config('dashboards.dashboard.mukurtu_dashboard')->isNew());
  }

  /**
   * The shipped dashboard config orders Security before Site information in the
   * Right column, so a fresh install matches the rebalanced layout (#1787).
   */
  public function testShippedDashboardConfigOrdersSecurityBeforeSiteInfo(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $profile_path = \Drupal::root() . '/' . dirname($module_path, 2);
    $data = Yaml::parseFile($profile_path . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml');
    $components = $data['sections'][0]['components'];
    $this->assertSame('three', $components[self::SECURITY_UUID]['region']);
    $this->assertSame('three', $components[self::SITE_INFO_UUID]['region']);
    $this->assertLessThan(
      $components[self::SITE_INFO_UUID]['weight'],
      $components[self::SECURITY_UUID]['weight'],
    );
  }

  /**
   * The Configure Community/Protocol Permissions links are removed (#1787).
   */
  public function testPermissionLinksRemoved(): void {
    $links = $this->menuLinks('mukurtu_protocol');
    $this->assertArrayNotHasKey('mukurtu_protocol.config_community_permissions', $links);
    $this->assertArrayNotHasKey('mukurtu_protocol.config_protocol_permissions', $links);
  }

  /**
   * The shared route the removed links used is still defined (#1787): only the
   * menu links go away, the OG permissions form stays reachable.
   */
  public function testPermissionsOverviewRouteStillExists(): void {
    $path = \Drupal::service('extension.list.module')->getPath('mukurtu_protocol');
    $routes = Yaml::parseFile(\Drupal::root() . '/' . $path . '/mukurtu_protocol.routing.yml');
    $this->assertArrayHasKey('mukurtu_protocol.permissions_overview', $routes);
  }

  /**
   * The Visitors dashboard links land in the Site settings section (#2057).
   */
  public function testVisitorsLinksAddedToSiteSettings(): void {
    $links = $this->menuLinks('mukurtu_core');

    foreach (['mukurtu_core.visitors_analytics' => 'visitors.index', 'mukurtu_core.visitors_settings' => 'visitors.settings'] as $id => $route_name) {
      $this->assertArrayHasKey($id, $links);
      $this->assertSame($route_name, $links[$id]['route_name']);
      $this->assertSame('dashboard-site-settings', $links[$id]['menu_name']);
      $this->assertSame('mukurtu_dashboard', $links[$id]['parent']);
    }
  }

  /**
   * The Multilingual section has exactly one "Manage site languages" link, and
   * it points at the languages overview page (#1787 part 3).
   */
  public function testMultilingualSectionHasNoDuplicateLanguagesLink(): void {
    $links = $this->menuLinks('mukurtu_multilingual');

    $languages_links = array_filter($links, static fn(array $link): bool => ($link['title'] ?? '') === 'Manage site languages');
    $this->assertCount(1, $languages_links);

    $link = reset($languages_links);
    $this->assertSame('internal:/admin/config/regional/language', $link['url']);
    $this->assertArrayNotHasKey('entity.configurable_language.edit_form', $links);
  }

  /**
   * mukurtu_core_update_40108() creates the two new dashboard menus and places
   * every block into the rebalanced three-column layout (#1787).
   */
  public function testUpdate40108SplitsAndRebalances(): void {
    $this->saveDashboardConfig(8, 9);

    mukurtu_core_update_40108();

    $menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
    $this->assertNotNull($menu_storage->load('dashboard-publication-tools'));
    $this->assertNotNull($menu_storage->load('dashboard-local-contexts'));

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $by_id = [];
    foreach ($components as $component) {
      $by_id[$component['configuration']['id']] = $component;
    }
    // The two new blocks land in their rebalanced homes: Local Contexts in the
    // Left column, Publication tools in the Middle column.
    $this->assertSame(['one', 6], [$by_id['system_menu_block:dashboard-local-contexts']['region'], $by_id['system_menu_block:dashboard-local-contexts']['weight']]);
    $this->assertSame(['two', 5], [$by_id['system_menu_block:dashboard-publication-tools']['region'], $by_id['system_menu_block:dashboard-publication-tools']['weight']]);
    // Existing blocks in the fixture are re-homed too.
    $this->assertSame(['three', 4], [$by_id['system_menu_block:dashboard-security']['region'], $by_id['system_menu_block:dashboard-security']['weight']]);
    $this->assertSame(['three', 5], [$by_id['system_menu_block:dashboard-site-info']['region'], $by_id['system_menu_block:dashboard-site-info']['weight']]);
  }

  /**
   * Running mukurtu_core_update_40108() twice does not duplicate blocks.
   */
  public function testUpdate40108IsIdempotent(): void {
    $this->saveDashboardConfig(8, 9);

    mukurtu_core_update_40108();
    mukurtu_core_update_40108();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $ids = array_map(static fn(array $c): string => $c['configuration']['id'], array_values($components));
    $this->assertSame(1, count(array_keys($ids, 'system_menu_block:dashboard-publication-tools', TRUE)));
    $this->assertSame(1, count(array_keys($ids, 'system_menu_block:dashboard-local-contexts', TRUE)));
  }

  /**
   * mukurtu_core_update_40110() creates the "Notifications & Review" menu,
   * inserts its block into the Right column directly after "My account", and
   * shifts the remaining Right-column blocks down (#2090).
   */
  public function testUpdate40110CreatesNotificationsReviewSection(): void {
    \Drupal::configFactory()->getEditable('dashboards.dashboard.mukurtu_dashboard')
      ->setData([
        'id' => 'mukurtu_dashboard',
        'sections' => [
          [
            'components' => [
              'my-account-uuid' => [
                'uuid' => 'my-account-uuid',
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-my-account'],
                'weight' => 1,
              ],
              'look-feel-uuid' => [
                'uuid' => 'look-feel-uuid',
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-look-feel'],
                'weight' => 2,
              ],
              self::SECURITY_UUID => [
                'uuid' => self::SECURITY_UUID,
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-security'],
                'weight' => 4,
              ],
              self::SITE_INFO_UUID => [
                'uuid' => self::SITE_INFO_UUID,
                'region' => 'three',
                'configuration' => ['id' => 'system_menu_block:dashboard-site-info'],
                'weight' => 5,
              ],
            ],
          ],
        ],
      ])
      ->save();

    mukurtu_core_update_40110();

    $menu_storage = \Drupal::entityTypeManager()->getStorage('menu');
    $this->assertNotNull($menu_storage->load('dashboard-notifications-review'));

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $by_id = [];
    foreach ($components as $component) {
      $by_id[$component['configuration']['id']] = $component;
    }
    $this->assertSame(['three', 1], [$by_id['system_menu_block:dashboard-my-account']['region'], $by_id['system_menu_block:dashboard-my-account']['weight']]);
    $this->assertSame(['three', 2], [$by_id['system_menu_block:dashboard-notifications-review']['region'], $by_id['system_menu_block:dashboard-notifications-review']['weight']]);
    $this->assertSame(['three', 3], [$by_id['system_menu_block:dashboard-look-feel']['region'], $by_id['system_menu_block:dashboard-look-feel']['weight']]);
    $this->assertSame(['three', 5], [$by_id['system_menu_block:dashboard-security']['region'], $by_id['system_menu_block:dashboard-security']['weight']]);
    $this->assertSame(['three', 6], [$by_id['system_menu_block:dashboard-site-info']['region'], $by_id['system_menu_block:dashboard-site-info']['weight']]);
  }

  /**
   * Running mukurtu_core_update_40110() twice does not duplicate the new
   * block (#2090).
   */
  public function testUpdate40110IsIdempotent(): void {
    $this->saveDashboardConfig();

    mukurtu_core_update_40110();
    mukurtu_core_update_40110();

    $components = \Drupal::config('dashboards.dashboard.mukurtu_dashboard')->get('sections.0.components');
    $ids = array_map(static fn(array $c): string => $c['configuration']['id'], array_values($components));
    $this->assertSame(1, count(array_keys($ids, 'system_menu_block:dashboard-notifications-review', TRUE)));
  }

  /**
   * mukurtu_core_update_40110() grants "administer comments" to the Mukurtu
   * Manager role so it can use the new comment moderation links (#2090).
   */
  public function testUpdate40110GrantsAdministerCommentsToManager(): void {
    \Drupal\user\Entity\Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();
    $this->assertFalse(\Drupal\user\Entity\Role::load('mukurtu_manager')->hasPermission('administer comments'));

    mukurtu_core_update_40110();

    $this->assertTrue(\Drupal\user\Entity\Role::load('mukurtu_manager')->hasPermission('administer comments'));
  }

  /**
   * The shipped dashboard config carries the Publication tools / Local Contexts
   * section blocks for fresh installs (#1787).
   */
  public function testShippedDashboardConfigHasNewSectionBlocks(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $profile_path = \Drupal::root() . '/' . dirname($module_path, 2);
    $data = Yaml::parseFile($profile_path . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml');

    $placement = [];
    foreach ($data['sections'][0]['components'] as $component) {
      $placement[$component['configuration']['id']] = [$component['region'], $component['weight']];
    }
    // Rebalanced homes: Local Contexts in Left, Publication tools in Middle.
    $this->assertSame(['one', 6], $placement['system_menu_block:dashboard-local-contexts']);
    $this->assertSame(['two', 5], $placement['system_menu_block:dashboard-publication-tools']);

    foreach (['dashboard-publication-tools' => 'Publication tools', 'dashboard-local-contexts' => 'Local Contexts'] as $id => $label) {
      $menu = Yaml::parseFile($profile_path . "/config/install/system.menu.$id.yml");
      $this->assertSame($id, $menu['id']);
      $this->assertSame($label, $menu['label']);
    }
  }

  /**
   * Publication-tools links (workflow settings, submission forms, and
   * site-wide comment settings) point at the new menu (#1787, #2090).
   */
  public function testPublicationToolsLinksMoved(): void {
    $expected = [
      'mukurtu_workflows' => ['mukurtu_workflows.settings'],
      'mukurtu_submissions' => ['entity.mukurtu_submission_settings.collection'],
      'mukurtu_protocol' => ['mukurtu_protocol.comment_settings'],
    ];
    foreach ($expected as $module => $ids) {
      $links = $this->menuLinks($module);
      foreach ($ids as $id) {
        $this->assertArrayHasKey($id, $links);
        $this->assertSame('dashboard-publication-tools', $links[$id]['menu_name']);
      }
    }
  }

  /**
   * The review/notification links land in the new "Notifications & Review"
   * section, and the new "Comment reviews" / "Protocol comment reviews"
   * links exist and point at the right routes (#2090).
   */
  public function testNotificationsAndReviewLinksMoved(): void {
    $expected = [
      'mukurtu_protocol' => [
        'mukurtu_protocol.comment_admin' => 'comment.admin',
        'mukurtu_protocol.my_unapproved_comments' => 'mukurtu_protocol.my_unapproved_comments',
      ],
      'mukurtu_workflows' => [
        'mukurtu_workflows.review_queue' => 'view.mukurtu_workflow_overview.review_queue',
      ],
      'mukurtu_submissions' => [
        'mukurtu_submissions.pending_queue' => 'view.mukurtu_pending_submissions.page',
      ],
      'mukurtu_notifications' => [
        'mukurtu_notifications.my_notifications' => 'view.mukurtu_message_log.mukurtu_notifications_page',
        'mukurtu_notifications.all_notifications' => 'view.mukurtu_message_log.mukurtu_notifications_admin_page',
      ],
    ];
    foreach ($expected as $module => $ids) {
      $links = $this->menuLinks($module);
      foreach ($ids as $id => $route_name) {
        $this->assertArrayHasKey($id, $links);
        $this->assertSame('dashboard-notifications-review', $links[$id]['menu_name']);
        $this->assertSame('mukurtu_dashboard', $links[$id]['parent']);
        $this->assertSame($route_name, $links[$id]['route_name']);
      }
    }
  }

  /**
   * All three Local Contexts links move to the new Local Contexts menu (#1787).
   */
  public function testLocalContextsLinksMoved(): void {
    $links = $this->menuLinks('mukurtu_local_contexts');
    $this->assertNotEmpty($links);
    foreach ($links as $link) {
      $this->assertSame('dashboard-local-contexts', $link['menu_name']);
    }
  }

  /**
   * The shipped dashboard config spreads blocks across the three columns per the
   * rebalanced layout: Left = content & community, Middle = editorial & data,
   * Right = site administration (#1787).
   */
  public function testShippedDashboardColumnsAreBalanced(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $profile_path = \Drupal::root() . '/' . dirname($module_path, 2);
    $data = Yaml::parseFile($profile_path . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml');

    $regions = ['one' => [], 'two' => [], 'three' => []];
    foreach ($data['sections'][0]['components'] as $component) {
      $regions[$component['region']][$component['configuration']['id']] = $component['weight'];
    }

    $this->assertEqualsCanonicalizing([
      'system_menu_block:dashboard-3Cs',
      'system_menu_block:dashboard-users',
      'system_menu_block:dashboard-content',
      'system_menu_block:dashboard-media',
      'system_menu_block:dashboard-taxonomies',
      'system_menu_block:dashboard-local-contexts',
    ], array_keys($regions['one']));

    $this->assertEqualsCanonicalizing([
      'mukurtu_setup_checklist',
      'views_block:content_recent-block_1',
      'system_menu_block:dashboard-roundtrip',
      'system_menu_block:dashboard-content-settings',
      'system_menu_block:dashboard-publication-tools',
      'system_menu_block:dashboard-multilingual',
      'system_menu_block:dashboard-migration',
    ], array_keys($regions['two']));

    $this->assertEqualsCanonicalizing([
      'system_menu_block:dashboard-my-account',
      'system_menu_block:dashboard-notifications-review',
      'system_menu_block:dashboard-look-feel',
      'system_menu_block:dashboard-site-settings',
      'system_menu_block:dashboard-security',
      'system_menu_block:dashboard-site-info',
    ], array_keys($regions['three']));

    // Weights within each column are unique so ordering is deterministic.
    foreach ($regions as $blocks) {
      $this->assertSame(array_values($blocks), array_unique(array_values($blocks)));
    }

    // Roundtrip sits directly below Recent Content, above the one-time
    // setup sections (Content settings, Publication tools, Multilingual)
    // and above Migration.
    $this->assertLessThan(
      $regions['two']['system_menu_block:dashboard-roundtrip'],
      $regions['two']['views_block:content_recent-block_1'],
    );
    foreach ([
      'system_menu_block:dashboard-content-settings',
      'system_menu_block:dashboard-publication-tools',
      'system_menu_block:dashboard-multilingual',
      'system_menu_block:dashboard-migration',
    ] as $id) {
      $this->assertLessThan($regions['two'][$id], $regions['two']['system_menu_block:dashboard-roundtrip']);
    }
  }

  /**
   * Links within each dashboard section render in the curated order (#1787).
   * Dynamic links (Landing Page, Mukurtu Version) are appended by
   * hook_menu_links_discovered_alter() and are not asserted here.
   */
  public function testLinkOrderWithinBlocks(): void {
    $expected = [
      'dashboard-3Cs' => [
        'Manage communities and cultural protocols',
        'Community organization',
        'Add a community',
        'Add a cultural protocol',
        'Manage categories',
      ],
      'dashboard-users' => [
        'Manage users',
        'Add user',
        'Create user with community membership',
        'Manage user settings and registration',
      ],
      'dashboard-content' => [
        'Manage Content',
        'Add collection',
        'Add dictionary word',
        'Add digital heritage item',
        'Add person record',
        'Add place record',
        'Add word list',
      ],
      'dashboard-media' => [
        'Manage Media',
        'Add Media',
        'Manage media tags',
        'Media download settings',
        'Media content warnings',
        'Default media thumbnails',
        'Media settings',
      ],
      'dashboard-local-contexts' => [
        'Manage site-wide Local Contexts projects',
        'View site-wide Local Contexts projects',
        'Remap Legacy Projects',
      ],
      'dashboard-publication-tools' => [
        'Publishing workflow',
        'Submission Forms',
        'Site-wide comment settings',
      ],
      'dashboard-notifications-review' => [
        'Comment reviews',
        'Protocol comment reviews',
        'Review queue',
        'Pending Submissions',
        'My notifications',
        'All notifications',
      ],
      'dashboard-multilingual' => [
        'Manage site languages',
        'Configure content translation',
        'Translate configuration',
        'Translate interface',
      ],
      'dashboard-roundtrip' => [
        'Export Lists',
        'Export Taxonomy',
        'Export Settings',
        'Import',
        'Import Logs',
        'Import format information.',
        'Manage Import Templates',
      ],
      'dashboard-look-feel' => [
        'Access Denied Page',
        'Change logo',
        'Color settings',
        'Configure consent popup',
        'Main navigation menu',
      ],
      'dashboard-site-settings' => [
        'Site Setup',
        'Site name and email',
        'Citation templates',
        'Cookie & Consent Settings',
        'Google Tag Settings',
        'Mukurtu search settings',
        'Notification messages',
        'Analytics',
        'Analytics settings',
      ],
    ];
    foreach ($expected as $menu_name => $titles) {
      $this->assertSame($titles, $this->dashboardMenuOrder($menu_name), "Order for $menu_name");
    }
  }

  /**
   * "Add a basic page" and "Site configuration" are dropped from the dashboard,
   * and "Access Denied Page" moves to the Look and feel section (#1787).
   */
  public function testLinksDroppedAndMoved(): void {
    $links = $this->menuLinks('mukurtu_core');
    $this->assertArrayNotHasKey('mukurtu_core.create_basic_page_dashboard', $links);
    $this->assertArrayNotHasKey('mukurtu_core.site_configuration', $links);
    $this->assertSame('dashboard-look-feel', $links['mukurtu_core.access_denied_settings']['menu_name']);
  }

  /**
   * The visitors.settings dashboard link is relabelled "Analytics settings"
   * (#1787).
   */
  public function testVisitorSettingsRelabelled(): void {
    $links = $this->menuLinks('mukurtu_core');
    $this->assertSame('Analytics settings', $links['mukurtu_core.visitors_settings']['title']);
  }

}
