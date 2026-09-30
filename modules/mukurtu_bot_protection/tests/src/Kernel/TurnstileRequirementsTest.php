<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_bot_protection\Kernel;

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\turnstile\Hook\TurnstileRequirementsHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests how the Turnstile missing-keys status report entry is adjusted.
 *
 * The hooks are exercised directly rather than through
 * moduleHandler()->invokeAll('runtime_requirements'), because core's own
 * SystemRequirementsHooks::checkRequirements() fatals in a kernel test on an
 * undefined drupal_verify_install_file() unless install.inc is loaded. The
 * turnstile module's own hook supplies the entry, so a change to its key or
 * shape fails here rather than silently bringing the error back. The alter
 * runs through the module handler, so the #[Hook] registration is covered too.
 *
 * @see \Drupal\mukurtu_bot_protection\Hook\TurnstileRequirements
 */
#[Group('mukurtu_bot_protection')]
class TurnstileRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'captcha',
    'key',
    'turnstile',
    'turnstile_protect',
    'mukurtu_bot_protection',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['captcha', 'turnstile', 'turnstile_protect']);
    // The warning links to the settings form by route.
    \Drupal::service('router.builder')->rebuild();
  }

  /**
   * Runs the turnstile module's hook, then Mukurtu's alter.
   */
  protected function requirements(): array {
    $requirements = (new TurnstileRequirementsHooks())->runtimeRequirements();
    $this->assertArrayHasKey('turnstile', $requirements, 'The turnstile module reports missing keys under the "turnstile" key.');

    \Drupal::moduleHandler()->alter('runtime_requirements', $requirements);

    return $requirements;
  }

  /**
   * Asserts that the entry is a warning pointing at the settings form.
   */
  protected function assertWarning(array $requirements): void {
    $this->assertArrayHasKey('turnstile', $requirements);
    $this->assertSame(RequirementSeverity::Warning, $requirements['turnstile']['severity']);
    $this->assertStringContainsString(
      '/admin/config/people/captcha/mukurtu-bot-protection',
      (string) $requirements['turnstile']['description']
    );
  }

  /**
   * Sets the challenge the captcha module falls back to.
   */
  protected function setDefaultChallenge(string $challenge): void {
    $this->config('captcha.settings')->set('default_challenge', $challenge)->save();
  }

  /**
   * Nothing is reported with the shipped defaults, where nothing uses it.
   */
  public function testRemovedWhenTurnstileUnused(): void {
    $this->assertArrayNotHasKey('turnstile', $this->requirements());
  }

  /**
   * An enabled point on a different challenge does not count as use.
   */
  public function testRemovedWhenEnabledPointsUseAnotherChallenge(): void {
    $this->setDefaultChallenge('captcha/Math');
    CaptchaPoint::load('user_login_form')->setStatus(TRUE)->save();

    $this->assertArrayNotHasKey('turnstile', $this->requirements());
  }

  /**
   * A Turnstile default challenge with every point disabled is unused.
   *
   * This is the state the settings form leaves behind after switching from
   * Turnstile to "None".
   */
  public function testRemovedWhenDefaultIsTurnstileButPointsDisabled(): void {
    $this->setDefaultChallenge('turnstile/Turnstile');

    $this->assertArrayNotHasKey('turnstile', $this->requirements());
  }

  /**
   * An enabled point that falls back to a Turnstile default is a warning.
   */
  public function testWarningWhenPointFallsBackToTurnstile(): void {
    $this->setDefaultChallenge('turnstile/Turnstile');
    CaptchaPoint::load('user_login_form')->setStatus(TRUE)->save();

    $this->assertWarning($this->requirements());
  }

  /**
   * An enabled point set to Turnstile directly is a warning.
   */
  public function testWarningWhenPointUsesTurnstileDirectly(): void {
    $this->setDefaultChallenge('captcha/Math');
    // setCaptchaType() returns nothing, so it cannot be chained.
    $point = CaptchaPoint::load('user_login_form');
    $point->setCaptchaType('turnstile/Turnstile');
    $point->setStatus(TRUE)->save();

    $this->assertWarning($this->requirements());
  }

  /**
   * A route behind turnstile_protect is a warning.
   */
  public function testWarningWhenRoutesAreProtected(): void {
    $this->config('turnstile_protect.settings')
      ->set('routes', ['user.login'])
      ->save();

    $this->assertWarning($this->requirements());
  }

  /**
   * Other requirements entries are left alone.
   */
  public function testOtherEntriesUntouched(): void {
    $requirements = [
      'other' => ['title' => 'Other', 'severity' => RequirementSeverity::Error],
    ];
    \Drupal::moduleHandler()->alter('runtime_requirements', $requirements);

    $this->assertSame(RequirementSeverity::Error, $requirements['other']['severity']);
  }

}
