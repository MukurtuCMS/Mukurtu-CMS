<?php

declare(strict_types=1);

namespace Drupal\mukurtu_bot_protection\Hook;

use Drupal\captcha\Constants\CaptchaConstants;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Tones down the Turnstile module's missing-keys status report entry.
 *
 * The turnstile module reports an error whenever its keys are empty, even
 * when nothing on the site uses Turnstile. Mukurtu installs Turnstile but
 * defaults to ALTCHA, so every new site would show that error for a feature
 * it never turned on. The entry is removed while Turnstile is unused, and
 * otherwise reported as a warning that points at Mukurtu's own settings form,
 * which is where Mukurtu stores the keys.
 */
class TurnstileRequirements {

  use StringTranslationTrait;

  /**
   * The challenge identifier the captcha module uses for Turnstile.
   */
  protected const TURNSTILE_CHALLENGE = 'turnstile/Turnstile';

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Implements hook_runtime_requirements_alter().
   */
  #[Hook('runtime_requirements_alter')]
  public function runtimeRequirementsAlter(array &$requirements): void {
    if (!isset($requirements['turnstile'])) {
      return;
    }

    if (!$this->turnstileInUse()) {
      unset($requirements['turnstile']);
      return;
    }

    $requirements['turnstile']['severity'] = RequirementSeverity::Warning;
    $requirements['turnstile']['description'] = $this->t('Turnstile has no site key or secret key. Add them on the <a href=":url">Bot & spam protection</a> page.', [
      ':url' => Url::fromRoute('mukurtu_bot_protection.settings')->toString(),
    ]);
  }

  /**
   * Whether any enabled CAPTCHA point or protected route uses Turnstile.
   *
   * A CAPTCHA point with no challenge of its own falls back to the default
   * challenge. CaptchaPoint::getCaptchaType() only resolves that fallback when
   * the type is unset, and points loaded from config store the literal
   * 'default' instead, so it is resolved here. Only enabled points count:
   * choosing "None" on the settings form disables the points but leaves the
   * default challenge as it was.
   */
  protected function turnstileInUse(): bool {
    if ($this->entityTypeManager->hasDefinition('captcha_point')) {
      $default = $this->configFactory->get('captcha.settings')->get('default_challenge');
      $points = $this->entityTypeManager->getStorage('captcha_point')
        ->loadByProperties(['status' => TRUE]);
      foreach ($points as $point) {
        $type = $point->getCaptchaType();
        if ($type === NULL || $type === CaptchaConstants::CAPTCHA_TYPE_DEFAULT) {
          $type = $default;
        }
        if ($type === static::TURNSTILE_CHALLENGE) {
          return TRUE;
        }
      }
    }

    return !empty($this->configFactory->get('turnstile_protect.settings')->get('routes'));
  }

}
