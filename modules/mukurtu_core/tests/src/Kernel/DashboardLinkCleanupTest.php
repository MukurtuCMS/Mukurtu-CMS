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
 * - The Configure Community/Protocol Permissions links are gone.
 * - The Visitors "Analytics" / "Visitor settings" links are present.
 * - The Multilingual section no longer has a duplicate "Manage site languages".
 *
 * @see mukurtu_core_update_40107()
 * @see mukurtu_core_update_40108()
 * @see mukurtu_protocol_update_40044()
 */
#[Group('mukurtu_core')]
class DashboardLinkCleanupTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
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
   * The shipped dashboard config already orders Security before Site info, so a
   * fresh install matches what the update hook produces (issue #1787 part 2).
   */
  public function testShippedDashboardConfigOrdersSecurityBeforeSiteInfo(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $profile_path = \Drupal::root() . '/' . dirname($module_path, 2);
    $data = Yaml::parseFile($profile_path . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml');
    $components = $data['sections'][0]['components'];
    $this->assertSame(8, $components[self::SECURITY_UUID]['weight']);
    $this->assertSame(9, $components[self::SITE_INFO_UUID]['weight']);
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
   * mukurtu_core_update_40108() creates the two new dashboard menus and adds
   * their section blocks to the Right column, re-weighted into order (#1787).
   */
  public function testUpdate40108SplitsSiteSettingsSection(): void {
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
    $this->assertArrayHasKey('system_menu_block:dashboard-publication-tools', $by_id);
    $this->assertArrayHasKey('system_menu_block:dashboard-local-contexts', $by_id);
    $this->assertSame('three', $by_id['system_menu_block:dashboard-publication-tools']['region']);
    $this->assertSame(5, $by_id['system_menu_block:dashboard-publication-tools']['weight']);
    $this->assertSame(6, $by_id['system_menu_block:dashboard-local-contexts']['weight']);
    // Existing Right-column blocks are re-weighted around the new ones.
    $this->assertSame(8, $by_id['system_menu_block:dashboard-security']['weight']);
    $this->assertSame(9, $by_id['system_menu_block:dashboard-site-info']['weight']);
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
   * The shipped dashboard config carries the Publication tools / Local Contexts
   * section blocks for fresh installs (#1787).
   */
  public function testShippedDashboardConfigHasNewSectionBlocks(): void {
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    $profile_path = \Drupal::root() . '/' . dirname($module_path, 2);
    $data = Yaml::parseFile($profile_path . '/config/install/dashboards.dashboard.mukurtu_dashboard.yml');

    $ids = [];
    foreach ($data['sections'][0]['components'] as $component) {
      $ids[$component['configuration']['id']] = $component['weight'];
    }
    $this->assertSame(5, $ids['system_menu_block:dashboard-publication-tools']);
    $this->assertSame(6, $ids['system_menu_block:dashboard-local-contexts']);

    foreach (['dashboard-publication-tools' => 'Publication tools', 'dashboard-local-contexts' => 'Local Contexts'] as $id => $label) {
      $menu = Yaml::parseFile($profile_path . "/config/install/system.menu.$id.yml");
      $this->assertSame($id, $menu['id']);
      $this->assertSame($label, $menu['label']);
    }
  }

  /**
   * Publication-tools links (workflow + submissions) point at the new menu with
   * no leftover weight overrides (#1787).
   */
  public function testPublicationToolsLinksMoved(): void {
    $expected = [
      'mukurtu_workflows' => ['mukurtu_workflows.settings', 'mukurtu_workflows.review_queue'],
      'mukurtu_submissions' => ['entity.mukurtu_submission_settings.collection', 'mukurtu_submissions.pending_queue'],
    ];
    foreach ($expected as $module => $ids) {
      $links = $this->menuLinks($module);
      foreach ($ids as $id) {
        $this->assertArrayHasKey($id, $links);
        $this->assertSame('dashboard-publication-tools', $links[$id]['menu_name']);
        $this->assertArrayNotHasKey('weight', $links[$id]);
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

}
