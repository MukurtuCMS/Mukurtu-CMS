<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Proves a module with no update hooks is installed at its last_removed value.
 *
 * This is the single assumption the whole 4.0.1 hook strip rests on. With every
 * hook_update_N() deleted there is no maximum update number for
 * ModuleInstaller::install() to seed a new module's schema version from, so it
 * falls back to hook_update_last_removed(). If that fallback did not happen,
 * a fresh install would sit at schema version 0, every removed update would
 * look outstanding, and mukurtu_core_update_requirements() would block the very
 * install it is meant to protect.
 *
 * Note that this cannot be asserted the obvious way. KernelTestBase's $modules
 * property enables modules directly rather than through ModuleInstaller, so no
 * schema version is written at all and the registry reports -1. The module has
 * to be installed through the real installer at runtime, which is what this
 * test does.
 *
 * mukurtu_gin_custom is used because it is the only Mukurtu module whose
 * dependencies are light enough to install in a kernel test, and it happens to
 * carry a distinctive last_removed value of 10000 rather than a 4000x one, so
 * the assertion cannot pass by coincidence.
 *
 * @see \Drupal\Core\Extension\ModuleInstaller::install()
 * @see \Drupal\mukurtu_core\Hook\UpdatePathRequirements
 */
#[Group('mukurtu')]
class UpdateBaselineSeedingTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * Installing through the real installer seeds the declared baseline.
   */
  public function testInstallerSeedsTheDeclaredBaseline(): void {
    $registry = $this->container->get('update.update_hook_registry');
    $moduleHandler = \Drupal::moduleHandler();

    $this->assertFalse($moduleHandler->moduleExists('mukurtu_gin_custom'), 'Precondition: the module is not installed yet.');

    $this->container->get('module_installer')->install(['mukurtu_gin_custom']);

    // Re-read the registry, since installing rebuilt the container.
    $registry = \Drupal::service('update.update_hook_registry');
    \Drupal::moduleHandler()->loadInclude('mukurtu_gin_custom', 'install');

    $this->assertSame(
      10000,
      mukurtu_gin_custom_update_last_removed(),
      'Precondition: the module declares the baseline this test expects.'
    );

    $this->assertSame(
      mukurtu_gin_custom_update_last_removed(),
      $registry->getInstalledVersion('mukurtu_gin_custom'),
      'A module with no update hooks must be installed at its hook_update_last_removed() value, or every fresh install looks out of date.'
    );
  }

  /**
   * The module really does ship with no update hooks left.
   *
   * Without this the test above could pass for the wrong reason: if the module
   * still had a hook_update_N() higher than its last_removed, the installer
   * would seed from that maximum instead and the fallback would never be
   * exercised.
   */
  public function testTheModuleHasNoUpdateHooksLeft(): void {
    $registry = $this->container->get('update.update_hook_registry');

    $this->assertSame(
      [],
      $registry->getAvailableUpdates('mukurtu_gin_custom'),
      'The module still has update hooks, so this test is not exercising the last_removed fallback.'
    );
  }

}
