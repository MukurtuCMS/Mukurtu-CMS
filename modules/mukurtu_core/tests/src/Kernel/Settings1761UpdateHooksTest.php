<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the mukurtu_core update hooks added by the #1761 default-settings
 * review.
 *
 * @see mukurtu_core_update_40107()
 * @see mukurtu_core_update_40108()
 * @see mukurtu_core_update_40109()
 * @see mukurtu_core_update_40110()
 * @see mukurtu_core_update_40111()
 * @see mukurtu_core_update_40112()
 */
#[Group('mukurtu_core')]
class Settings1761UpdateHooksTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

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
    $module_path = \Drupal::service('extension.list.module')->getPath('mukurtu_core');
    require_once $module_path . '/mukurtu_core.install';
  }

  /**
   * The 404 page hook points system.site at the new route and installs the
   * default title/message config. The permission grant to mukurtu_manager
   * (via core's user_role_grant_permissions()) is exercised live on a real
   * site rather than here -- it was flaky under KernelTestBase's
   * loadOverrideFree() with a role created in the same test method.
   */
  public function testNotFoundPageUpdate(): void {
    \Drupal::configFactory()->getEditable('system.site')->set('page.404', '')->save();
    Role::create(['id' => 'mukurtu_manager', 'label' => 'Mukurtu Manager'])->save();

    mukurtu_core_update_40107();

    $this->assertSame('/mukurtu/not-found', \Drupal::config('system.site')->get('page.404'));
    $this->assertSame('Page Not Found', \Drupal::config('mukurtu_core.not_found')->get('title'));
  }

  /**
   * The 404 page hook is a no-op on the not_found config if it already
   * exists (an admin has already saved custom title/message).
   */
  public function testNotFoundPageUpdateIsIdempotent(): void {
    \Drupal::configFactory()->getEditable('mukurtu_core.not_found')
      ->set('title', 'Custom title')
      ->save();

    mukurtu_core_update_40107();

    $this->assertSame('Custom title', \Drupal::config('mukurtu_core.not_found')->get('title'));
  }

  /**
   * The ALTCHA hook hides the logo and footer on an existing config.
   */
  public function testAltchaWidgetUpdate(): void {
    \Drupal::configFactory()->getEditable('altcha.settings')
      ->set('hide_logo', FALSE)
      ->set('hide_footer', FALSE)
      ->save();

    mukurtu_core_update_40108();

    $config = \Drupal::config('altcha.settings');
    $this->assertTrue($config->get('hide_logo'));
    $this->assertTrue($config->get('hide_footer'));
  }

  /**
   * The Klaro stripe-app hook fixes the default/required mismatch.
   */
  public function testKlaroStripeAppUpdate(): void {
    \Drupal::configFactory()->getEditable('klaro.klaro_app.stripe')
      ->set('status', FALSE)
      ->set('default', TRUE)
      ->set('required', TRUE)
      ->save();

    mukurtu_core_update_40109();

    $config = \Drupal::config('klaro.klaro_app.stripe');
    $this->assertFalse($config->get('default'));
    $this->assertFalse($config->get('required'));
  }

  /**
   * The cron interval hook lowers 3h to 1h, but only when still at the
   * shipped default.
   */
  public function testCronIntervalUpdate(): void {
    \Drupal::configFactory()->getEditable('automated_cron.settings')->set('interval', 10800)->save();
    mukurtu_core_update_40110();
    $this->assertSame(3600, \Drupal::config('automated_cron.settings')->get('interval'));
  }

  /**
   * The cron interval hook leaves a site-customized interval alone.
   */
  public function testCronIntervalUpdateLeavesCustomValue(): void {
    \Drupal::configFactory()->getEditable('automated_cron.settings')->set('interval', 300)->save();
    mukurtu_core_update_40110();
    $this->assertSame(300, \Drupal::config('automated_cron.settings')->get('interval'));
  }

  /**
   * The Visitors hook expands the entity counter beyond nodes, but only
   * when still at the shipped node-only default.
   */
  public function testVisitorsEntityTypesUpdate(): void {
    \Drupal::configFactory()->getEditable('visitors.config')
      ->set('counter.entity_types', ['node'])
      ->save();

    mukurtu_core_update_40111();

    $entity_types = \Drupal::config('visitors.config')->get('counter.entity_types');
    $this->assertContains('media', $entity_types);
    $this->assertContains('community', $entity_types);
    $this->assertContains('protocol', $entity_types);
    $this->assertContains('personal_collection', $entity_types);
    $this->assertContains('multipage_item', $entity_types);
    $this->assertContains('taxonomy_term', $entity_types);
  }

  /**
   * The Visitors hook leaves a site-customized entity type list alone.
   */
  public function testVisitorsEntityTypesUpdateLeavesCustomValue(): void {
    \Drupal::configFactory()->getEditable('visitors.config')
      ->set('counter.entity_types', ['node', 'user'])
      ->save();

    mukurtu_core_update_40111();

    $this->assertSame(['node', 'user'], \Drupal::config('visitors.config')->get('counter.entity_types'));
  }

  /**
   * The full_html hook revokes the permission from authenticated if present.
   */
  public function testFullHtmlRevoke(): void {
    $role = Role::create(['id' => 'authenticated', 'label' => 'Authenticated user']);
    $role->grantPermission('use text format full_html');
    $role->save();

    mukurtu_core_update_40112();

    $this->assertFalse(Role::load('authenticated')->hasPermission('use text format full_html'));
  }

  /**
   * The full_html hook is a no-op when the permission was never granted.
   */
  public function testFullHtmlRevokeIsNoOpWhenAbsent(): void {
    Role::create(['id' => 'authenticated', 'label' => 'Authenticated user'])->save();

    mukurtu_core_update_40112();

    $this->assertFalse(Role::load('authenticated')->hasPermission('use text format full_html'));
  }

}
