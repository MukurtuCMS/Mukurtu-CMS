<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\KlaroLifecycleHooks;
use Drupal\mukurtu_core\Service\KlaroConfigKeeper;
use Drupal\user\Entity\Role;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests Klaro configuration survives an uninstall and re-install.
 *
 * The keeper's service is registered by hand, and its hooks called directly,
 * so the test does not need mukurtu_core's full dependency tree.
 */
#[CoversClass(KlaroConfigKeeper::class)]
#[CoversClass(KlaroLifecycleHooks::class)]
#[Group('mukurtu_core')]
class KlaroConfigKeeperTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    // mukurtu_core is not enabled, so register the hooks' service by hand.
    $container->register('mukurtu_core.klaro_config_keeper', KlaroConfigKeeper::class)
      ->setArguments(array_map(fn (string $id) => new Reference($id), [
        'config.storage',
        'config.factory',
        'config.manager',
        'entity_type.manager',
        'config.typed',
        'state',
        'extension.list.profile',
      ]));
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Uninstalling a module clears its rows from users_data.
    $this->installSchema('user', ['users_data']);
    foreach (['anonymous', 'authenticated', 'editor'] as $id) {
      Role::create(['id' => $id, 'label' => $id])->save();
    }
    $this->container->get('module_installer')->install(['klaro']);
  }

  /**
   * Returns the keeper from the current container.
   */
  protected function keeper(): KlaroConfigKeeper {
    return \Drupal::service('mukurtu_core.klaro_config_keeper');
  }

  /**
   * Uninstalls and re-installs Klaro around a snapshot, then restores.
   */
  protected function reinstallKlaro(bool $snapshot = TRUE): bool {
    if ($snapshot) {
      $this->keeper()->snapshot();
    }
    $installer = \Drupal::service('module_installer');
    $installer->uninstall(['klaro']);
    $this->assertTrue(\Drupal::config('klaro.settings')->isNew());
    $installer->install(['klaro']);
    return $this->keeper()->restore();
  }

  /**
   * Whether the given role has the "use klaro" permission.
   */
  protected function roleCanUseKlaro(string $id): bool {
    return Role::load($id)->hasPermission('use klaro');
  }

  /**
   * The site's own configuration comes back exactly.
   */
  public function testRestoresPreviousConfig(): void {
    \Drupal::configFactory()->getEditable('klaro.settings')->set('disable_urls', ['^\/custom'])->save();
    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $custom = $storage->load('youtube')->createDuplicate();
    $custom->set('id', 'custom_embed')->set('label', 'Custom embed')->save();
    $storage->load('vimeo')->delete();
    Role::load('editor')->grantPermission('use klaro')->save();

    $this->assertTrue($this->reinstallKlaro());

    $this->assertSame(['^\/custom'], \Drupal::config('klaro.settings')->get('disable_urls'));
    $storage = \Drupal::entityTypeManager()->getStorage('klaro_app');
    $this->assertNotNull($storage->load('custom_embed'));
    $this->assertNull($storage->load('vimeo'));
    $this->assertTrue($this->roleCanUseKlaro('editor'));
    $this->assertFalse($this->roleCanUseKlaro('anonymous'));
    $this->assertNull(\Drupal::state()->get(KlaroConfigKeeper::STATE_KEY));
  }

  /**
   * With nothing saved, the profile's defaults are applied.
   */
  public function testAppliesDefaultsWithoutSnapshot(): void {
    $this->assertFalse($this->reinstallKlaro(FALSE));
    $this->assertDefaultsApplied();
  }

  /**
   * A snapshot that no longer matches Klaro's schema falls back to defaults.
   */
  public function testInvalidSnapshotFallsBackToDefaults(): void {
    $this->keeper()->snapshot();
    $snapshot = \Drupal::state()->get(KlaroConfigKeeper::STATE_KEY);
    $snapshot['config']['klaro.settings']['disable_urls'] = 'not a sequence';
    \Drupal::state()->set(KlaroConfigKeeper::STATE_KEY, $snapshot);

    $this->assertFalse($this->reinstallKlaro(FALSE));
    $this->assertDefaultsApplied();
  }

  /**
   * The hooks only act on Klaro, and never during a config sync.
   */
  public function testHooksIgnoreOtherModulesAndSync(): void {
    $hooks = new KlaroLifecycleHooks();

    $hooks->modulePreuninstall('node');
    $hooks->modulePreuninstall('klaro', TRUE);
    $this->assertNull(\Drupal::state()->get(KlaroConfigKeeper::STATE_KEY));

    $hooks->modulePreuninstall('klaro');
    $this->assertIsArray(\Drupal::state()->get(KlaroConfigKeeper::STATE_KEY));

    $hooks->modulesInstalled(['klaro'], TRUE);
    $this->assertIsArray(\Drupal::state()->get(KlaroConfigKeeper::STATE_KEY));
  }

  /**
   * Asserts the Mukurtu profile's Klaro defaults are in place.
   */
  protected function assertDefaultsApplied(): void {
    $this->assertContains('^\/visitors', \Drupal::config('klaro.settings')->get('disable_urls'));
    $this->assertNotNull(\Drupal::entityTypeManager()->getStorage('klaro_app')->load('soundcloud'));
    $this->assertTrue($this->roleCanUseKlaro('anonymous'));
    $this->assertTrue($this->roleCanUseKlaro('authenticated'));
    $this->assertFalse($this->roleCanUseKlaro('editor'));
  }

}
