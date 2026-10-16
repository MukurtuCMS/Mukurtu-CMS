<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_gin_custom\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests mukurtu_gin_custom does not require Klaro.
 *
 * mukurtu_gin_custom is a hard profile dependency. It used to depend on Klaro
 * only to ship the Toastify klaro_app, which made Klaro impossible to
 * uninstall. The klaro_app now ships as optional config instead.
 */
#[Group('mukurtu_gin_custom')]
class KlaroOptionalTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'user'];

  /**
   * The module declares no dependency on Klaro.
   */
  public function testNoKlaroDependency(): void {
    $info = $this->container->get('extension.list.module')->getExtensionInfo('mukurtu_gin_custom');
    $this->assertNotContains('klaro:klaro', $info['dependencies'] ?? []);
    $this->assertNotContains('klaro', $info['dependencies'] ?? []);
  }

  /**
   * The module installs without Klaro, and the Toastify app follows Klaro.
   */
  public function testToastifyAppInstalledWithKlaro(): void {
    $installer = $this->container->get('module_installer');

    $installer->install(['mukurtu_gin_custom']);
    $this->assertTrue($this->container->get('module_handler')->moduleExists('mukurtu_gin_custom'));
    $this->assertFalse($this->container->get('module_handler')->moduleExists('klaro'));
    $this->assertTrue($this->config('klaro.klaro_app.toastify')->isNew());

    $installer->install(['klaro']);
    $this->assertFalse($this->config('klaro.klaro_app.toastify')->isNew());
    $this->assertTrue($this->config('klaro.klaro_app.toastify')->get('required'));

    $installer->uninstall(['klaro']);
    $this->assertTrue($this->config('klaro.klaro_app.toastify')->isNew());
    $this->assertTrue($this->container->get('module_handler')->moduleExists('mukurtu_gin_custom'));
  }

}
