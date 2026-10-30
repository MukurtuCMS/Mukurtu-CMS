<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_bot_protection\Kernel;

use Drupal\KernelTests\KernelTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that CAPTCHA is not added to every form.
 *
 * The captcha module's "Add CAPTCHA challenges on all forms" setting puts a
 * challenge on every form, not just the CAPTCHA points Mukurtu enables. It
 * must be off on new sites and is turned off on existing ones.
 */
#[Group('mukurtu_bot_protection')]
class CaptchaGlobalDefaultTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'captcha',
    'honeypot',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['captcha', 'honeypot']);
    require_once dirname(__DIR__, 3) . '/mukurtu_bot_protection.install';
  }

  /**
   * Sets the captcha module's "all forms" setting.
   */
  protected function setEnableGlobally(bool $enabled): void {
    $this->config('captcha.settings')->set('enable_globally', $enabled)->save();
  }

  /**
   * Returns the captcha module's "all forms" setting.
   */
  protected function getEnableGlobally(): bool {
    return (bool) $this->config('captcha.settings')->get('enable_globally');
  }

  /**
   * Installing the module turns the setting off.
   */
  public function testInstallDisablesGlobalCaptcha(): void {
    $this->setEnableGlobally(TRUE);

    mukurtu_bot_protection_install(FALSE);

    $this->assertFalse($this->getEnableGlobally());
    $this->assertSame('altcha/ALTCHA', $this->config('captcha.settings')->get('default_challenge'));
  }

  /**
   * The update hook turns the setting off and reports it.
   */
  public function testUpdateTurnsOffGlobalCaptcha(): void {
    $this->setEnableGlobally(TRUE);

    $message = mukurtu_bot_protection_update_40002();

    $this->assertFalse($this->getEnableGlobally());
    $this->assertNotEmpty((string) $message);
  }

  /**
   * The update hook is silent when the setting is already off.
   */
  public function testUpdateLeavesDisabledSettingAlone(): void {
    $this->setEnableGlobally(FALSE);

    $this->assertNull(mukurtu_bot_protection_update_40002());
    $this->assertFalse($this->getEnableGlobally());
  }

}
