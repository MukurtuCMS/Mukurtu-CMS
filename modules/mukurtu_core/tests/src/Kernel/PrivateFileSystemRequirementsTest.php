<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\PrivateFileSystemRequirements;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the status report requirement for the private file system.
 *
 * The hook is exercised directly rather than through
 * moduleHandler()->invokeAll('runtime_requirements'), because core's own
 * SystemRequirementsHooks fatals in a kernel test on an undefined
 * drupal_verify_install_file() unless install.inc is loaded.
 *
 * @see \Drupal\mukurtu_core\Hook\PrivateFileSystemRequirements
 */
#[Group('mukurtu_core')]
class PrivateFileSystemRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  protected const KEY = 'mukurtu_core_private_file_system';

  /**
   * Runs the hook against the current settings.
   *
   * @param bool|null $private_scheme_valid
   *   Whether to report private:// as a registered scheme. NULL uses the real
   *   stream wrapper manager. Passing a value stubs it, because a kernel test
   *   cannot make the container register private:// from a setting alone -
   *   a real site needs a cache rebuild for that, which is precisely the step
   *   the requirement's description warns about.
   */
  protected function requirements(?bool $private_scheme_valid = NULL): array {
    if ($private_scheme_valid === NULL) {
      $manager = \Drupal::service('stream_wrapper_manager');
    }
    else {
      $manager = $this->createMock(StreamWrapperManagerInterface::class);
      $manager->method('isValidScheme')->willReturn($private_scheme_valid);
    }

    return (new PrivateFileSystemRequirements($manager))->runtimeRequirements();
  }

  /**
   * An unset private file path is reported as an error.
   *
   * KernelTestBase does not set file_private_path, so this is the default
   * state and matches a site that never configured it.
   */
  public function testErrorWhenPrivatePathUnset(): void {
    $this->setSetting('file_private_path', '');

    $requirements = $this->requirements();

    $this->assertArrayHasKey(static::KEY, $requirements);
    $this->assertSame(
      RequirementSeverity::Error,
      $requirements[static::KEY]['severity']
    );
    $this->assertSame(
      'Not configured',
      (string) $requirements[static::KEY]['value']
    );
    // The description has to tell the operator about the cache rebuild, which
    // is the step people miss.
    $this->assertStringContainsString(
      'clear the site cache',
      (string) $requirements[static::KEY]['description']
    );
    $this->assertStringContainsString(
      'file_private_path',
      (string) $requirements[static::KEY]['description']
    );
  }

  /**
   * A configured and registered private file system reports OK.
   */
  public function testOkWhenPrivatePathConfigured(): void {
    $dir = $this->siteDirectory . '/private';
    mkdir($dir, 0775, TRUE);
    $this->setSetting('file_private_path', $dir);

    $requirements = $this->requirements(TRUE);

    $this->assertSame(
      RequirementSeverity::OK,
      $requirements[static::KEY]['severity']
    );
    $this->assertSame(
      'Configured',
      (string) $requirements[static::KEY]['value']
    );
    $this->assertArrayNotHasKey('description', $requirements[static::KEY]);
  }

  /**
   * A path set but not yet registered still reports an error.
   *
   * This is the state a site is in immediately after editing settings.php and
   * before rebuilding caches, and it is why the description tells the operator
   * to clear the cache.
   */
  public function testErrorWhenPathSetButSchemeUnregistered(): void {
    $dir = $this->siteDirectory . '/private';
    if (!is_dir($dir)) {
      mkdir($dir, 0775, TRUE);
    }
    $this->setSetting('file_private_path', $dir);

    $requirements = $this->requirements(FALSE);

    $this->assertSame(
      RequirementSeverity::Error,
      $requirements[static::KEY]['severity']
    );
  }

  /**
   * The requirement is keyed distinctly so it cannot collide.
   */
  public function testRequirementKeyIsNamespaced(): void {
    $this->assertSame([static::KEY], array_keys($this->requirements(TRUE)));
  }

}
