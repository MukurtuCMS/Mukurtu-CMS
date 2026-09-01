<?php

namespace Drupal\Tests\mukurtu_bot_protection\Kernel;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests that the altcha module still fulfills the hook_captcha() contract
 * mukurtu_bot_protection relies on after upgrading drupal/altcha.
 *
 * @group mukurtu_bot_protection
 */
class AltchaCaptchaBackendTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'captcha',
    'altcha',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['altcha']);
    // The default 'self_hosted' integration type builds its challenge URL
    // via Url::fromRoute('altcha.challenge'), which needs a built router.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Mukurtu's backend picker only offers ALTCHA if it's a listed challenge.
   */
  public function testAltchaIsListedAsChallengeType(): void {
    $types = \Drupal::moduleHandler()->invoke('altcha', 'captcha', ['list']);
    $this->assertContains('ALTCHA', $types);
  }

  /**
   * BotProtectionSettingsForm sets default_challenge to 'altcha/ALTCHA';
   * this confirms that identifier still resolves to a working challenge.
   */
  public function testAltchaGeneratesChallengeMarkup(): void {
    $challenge = \Drupal::moduleHandler()->invoke('altcha', 'captcha', ['generate', 'ALTCHA']);

    $this->assertIsArray($challenge);
    $this->assertArrayHasKey('form', $challenge);
    $this->assertArrayHasKey('captcha_validate', $challenge);
    $this->assertSame('altcha_captcha_validation', $challenge['captcha_validate']);

    $widget = $challenge['form']['captcha_response'];
    $this->assertSame('altcha_widget', $widget['#theme']);
    $this->assertArrayHasKey('challenge', $widget['#attributes']);
  }

}
