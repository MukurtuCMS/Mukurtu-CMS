<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\DevelopmentModuleRequirements;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the status report warning for leftover development modules.
 *
 * The hook is exercised directly rather than through
 * moduleHandler()->invokeAll('runtime_requirements'), because core's own
 * SystemRequirementsHooks::checkRequirements() fatals in a kernel test on an
 * undefined drupal_verify_install_file() unless install.inc is loaded. Testing
 * this class in isolation keeps the test about Mukurtu's behaviour.
 *
 * @see \Drupal\mukurtu_core\Hook\DevelopmentModuleRequirements
 */
#[Group('mukurtu_core')]
class DevelopmentModuleRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  protected const KEY = 'mukurtu_core_development_modules';

  /**
   * Runs the hook against the current module list.
   */
  protected function requirements(): array {
    $hook = new DevelopmentModuleRequirements(\Drupal::moduleHandler());

    return $hook->runtimeRequirements();
  }

  /**
   * Nothing is reported when no development module is installed.
   */
  public function testNoWarningWhenNothingInstalled(): void {
    $this->assertSame([], $this->requirements());
  }

  /**
   * A warning is reported when devel is installed.
   */
  public function testWarningWhenDevelInstalled(): void {
    if (!\Drupal::service('extension.list.module')->exists('devel')) {
      $this->markTestSkipped('The devel module is not present on disk.');
    }
    $this->enableModules(['devel']);

    $requirements = $this->requirements();

    $this->assertArrayHasKey(static::KEY, $requirements);
    $this->assertSame(
      RequirementSeverity::Warning,
      $requirements[static::KEY]['severity']
    );
    $this->assertStringContainsString(
      'Devel',
      (string) $requirements[static::KEY]['value']
    );
    // The description points the operator at the uninstall page.
    $this->assertStringContainsString(
      '/admin/modules/uninstall',
      (string) $requirements[static::KEY]['description']
    );
  }

  /**
   * Every installed development module is named in the reported value.
   */
  public function testWarningNamesEachInstalledModule(): void {
    if (!\Drupal::service('extension.list.module')->exists('devel')) {
      $this->markTestSkipped('The devel module is not present on disk.');
    }
    $this->enableModules(['devel', 'devel_generate']);

    $value = (string) $this->requirements()[static::KEY]['value'];

    $this->assertStringContainsString('Devel', $value);
    $this->assertStringContainsString('Devel Generate', $value);
  }

}
