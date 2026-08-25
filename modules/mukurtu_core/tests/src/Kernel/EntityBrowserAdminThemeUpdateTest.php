<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\entity_browser\Entity\EntityBrowser;
use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_update_40004(), which flags entity browsers admin-themed.
 *
 * @see mukurtu_update_40004()
 */
#[Group('mukurtu_core')]
class EntityBrowserAdminThemeUpdateTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'entity_browser',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['entity_browser']);

    $profile_path = \Drupal::service('extension.list.profile')->getPath('mukurtu');
    require_once $profile_path . '/mukurtu.install';
  }

  /**
   * Creates a minimal entity browser for a given machine name.
   */
  protected function createBrowser(string $id, bool $useAdminTheme = FALSE): EntityBrowser {
    $browser = EntityBrowser::create([
      'name' => $id,
      'label' => $id,
      'display' => 'modal',
      'display_configuration' => ['use_admin_theme' => $useAdminTheme],
      'selection_display' => 'no_display',
      'widget_selector' => 'single',
      'widgets' => [],
    ]);
    $browser->save();
    return $browser;
  }

  /**
   * The update hook flags each shipped browser to use the admin theme.
   */
  public function testUpdateFlagsShippedBrowsers(): void {
    $this->createBrowser('mukurtu_content_browser');
    $this->createBrowser('mukurtu_collection_browser');

    mukurtu_update_40004();

    $content_browser = EntityBrowser::load('mukurtu_content_browser');
    $this->assertTrue($content_browser->display_configuration['use_admin_theme']);

    $collection_browser = EntityBrowser::load('mukurtu_collection_browser');
    $this->assertTrue($collection_browser->display_configuration['use_admin_theme']);
  }

  /**
   * The update hook is idempotent when the setting is already TRUE.
   */
  public function testUpdateIsIdempotent(): void {
    $this->createBrowser('mukurtu_content_browser', TRUE);

    mukurtu_update_40004();

    $browser = EntityBrowser::load('mukurtu_content_browser');
    $this->assertTrue($browser->display_configuration['use_admin_theme']);
  }

  /**
   * The update hook leaves browsers outside its list untouched.
   */
  public function testUpdateIgnoresUnlistedBrowsers(): void {
    $this->createBrowser('some_other_browser');

    mukurtu_update_40004();

    $browser = EntityBrowser::load('some_other_browser');
    $this->assertFalse($browser->display_configuration['use_admin_theme']);
  }

  /**
   * The update hook is a no-op when none of the shipped browsers exist.
   */
  public function testUpdateIsNoOpWithoutBrowsers(): void {
    mukurtu_update_40004();
    $this->assertNull(EntityBrowser::load('mukurtu_content_browser'));
  }

}
