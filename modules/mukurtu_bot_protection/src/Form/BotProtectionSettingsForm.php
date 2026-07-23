<?php

namespace Drupal\mukurtu_bot_protection\Form;

use Drupal\captcha\Entity\CaptchaPoint;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\key\Entity\Key;

/**
 * Chooses the active CAPTCHA backend and stores its credentials.
 */
class BotProtectionSettingsForm extends FormBase {

  /**
   * Config name used by the CAPTCHA module for its site-wide settings.
   */
  const CAPTCHA_SETTINGS = 'captcha.settings';

  /**
   * Config name used by the reCAPTCHA module for its credentials.
   */
  const RECAPTCHA_SETTINGS = 'recaptcha.settings';

  /**
   * Config name used by the Turnstile module for its settings.
   */
  const TURNSTILE_SETTINGS = 'turnstile.settings';

  /**
   * Machine name of the Key entity Mukurtu manages for Turnstile.
   */
  const TURNSTILE_KEY_ID = 'mukurtu_turnstile';

  /**
   * Config name used by the Honeypot module for its settings.
   */
  const HONEYPOT_SETTINGS = 'honeypot.settings';

  /**
   * Forms that are enabled/disabled together based on the backend, and that
   * Honeypot protection is independently toggled on/off for.
   */
  const MANAGED_CAPTCHA_POINTS = [
    'user_register_form',
    'user_pass',
    'user_login_form',
    'contact_message_feedback_form',
    'comment_comment_form',
  ];

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_bot_protection_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_challenge = $this->config(self::CAPTCHA_SETTINGS)->get('default_challenge');
    $backend = match ($default_challenge) {
      'recaptcha/reCAPTCHA' => 'recaptcha',
      'turnstile/Turnstile' => 'turnstile',
      'altcha/ALTCHA' => 'altcha',
      'captcha/Math' => 'basic',
      default => 'none',
    };

    $recaptcha_settings = $this->config(self::RECAPTCHA_SETTINGS);
    $recaptcha_secret = $recaptcha_settings->get('secret_key');

    $turnstile_key_values = [];
    if ($turnstile_key = Key::load(self::TURNSTILE_KEY_ID)) {
      $turnstile_key_values = $turnstile_key->getKeyValues() ?: [];
    }

    $form['backend'] = [
      '#type' => 'radios',
      '#title' => $this->t('CAPTCHA backend'),
      '#description' => $this->t('Basic challenge and ALTCHA require no external account and work immediately. reCAPTCHA and Cloudflare Turnstile require you to register your site with Google or Cloudflare and enter the resulting keys below before they can be selected.'),
      '#default_value' => $backend,
      '#options' => [
        'none' => $this->t('No challenge (not recommended)'),
        'basic' => $this->t('Basic challenge (default, no account needed)'),
        'recaptcha' => $this->t('Google reCAPTCHA'),
        'turnstile' => $this->t('Cloudflare Turnstile'),
        'altcha' => $this->t('ALTCHA (proof-of-work, no account needed)'),
      ],
    ];

    $form['recaptcha'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Google reCAPTCHA keys'),
      '#states' => [
        'visible' => [
          ':input[name="backend"]' => ['value' => 'recaptcha'],
        ],
      ],
    ];
    $form['recaptcha']['recaptcha_site_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site key'),
      '#default_value' => $recaptcha_settings->get('site_key'),
      '#states' => [
        'required' => [
          ':input[name="backend"]' => ['value' => 'recaptcha'],
        ],
      ],
    ];
    $form['recaptcha']['recaptcha_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key'),
      '#default_value' => $recaptcha_secret ? $this->maskSecret($recaptcha_secret) : '',
      '#states' => [
        'required' => [
          ':input[name="backend"]' => ['value' => 'recaptcha'],
        ],
      ],
    ];
    if ($recaptcha_secret) {
      $form['recaptcha']['recaptcha_secret_key']['#description'] = $this->t('Leave unchanged to keep the currently stored secret key.');
      $form['recaptcha']['recaptcha_clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear reCAPTCHA credentials'),
        '#validate' => [],
        '#submit' => ['::clearRecaptchaCredentials'],
      ];
    }

    $form['turnstile'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Cloudflare Turnstile keys'),
      '#states' => [
        'visible' => [
          ':input[name="backend"]' => ['value' => 'turnstile'],
        ],
      ],
    ];
    $form['turnstile']['turnstile_site_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Site key'),
      '#default_value' => $turnstile_key_values['site_key'] ?? '',
      '#states' => [
        'required' => [
          ':input[name="backend"]' => ['value' => 'turnstile'],
        ],
      ],
    ];
    $form['turnstile']['turnstile_secret_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret key'),
      '#default_value' => !empty($turnstile_key_values['secret_key']) ? $this->maskSecret($turnstile_key_values['secret_key']) : '',
      '#states' => [
        'required' => [
          ':input[name="backend"]' => ['value' => 'turnstile'],
        ],
      ],
    ];
    if (!empty($turnstile_key_values['secret_key'])) {
      $form['turnstile']['turnstile_secret_key']['#description'] = $this->t('Leave unchanged to keep the currently stored secret key.');
      $form['turnstile']['turnstile_clear'] = [
        '#type' => 'submit',
        '#value' => $this->t('Clear Turnstile credentials'),
        '#validate' => [],
        '#submit' => ['::clearTurnstileCredentials'],
      ];
    }

    $honeypot_form_settings = $this->config(self::HONEYPOT_SETTINGS)->get('form_settings') ?? [];
    $honeypot_enabled = !empty(array_intersect_key(array_filter($honeypot_form_settings), array_flip(self::MANAGED_CAPTCHA_POINTS)));

    $form['honeypot'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable Honeypot spam protection'),
      '#description' => $this->t("Adds a hidden field and a minimum time delay to catch automated form submissions. Works alongside any CAPTCHA backend above, including 'No challenge'."),
      '#default_value' => $honeypot_enabled,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save configuration'),
    ];

    return $form;
  }

  /**
   * Masks a stored secret for display, matching the Local Contexts pattern.
   */
  protected function maskSecret(string $secret): string {
    return substr($secret, 0, 6) . str_repeat('X', max(strlen($secret) - 6, 0));
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $backend = $form_state->getValue('backend');

    if ($backend === 'recaptcha') {
      $site_key = trim((string) $form_state->getValue('recaptcha_site_key'));
      $secret_key = trim((string) $form_state->getValue('recaptcha_secret_key'));
      $has_stored_secret = (bool) $this->config(self::RECAPTCHA_SETTINGS)->get('secret_key');
      if ($site_key === '') {
        $form_state->setErrorByName('recaptcha_site_key', $this->t('Enter a site key before selecting Google reCAPTCHA as the backend.'));
      }
      if ($secret_key === '' && !$has_stored_secret) {
        $form_state->setErrorByName('recaptcha_secret_key', $this->t('Enter a secret key before selecting Google reCAPTCHA as the backend.'));
      }
    }

    if ($backend === 'turnstile') {
      $site_key = trim((string) $form_state->getValue('turnstile_site_key'));
      $secret_key = trim((string) $form_state->getValue('turnstile_secret_key'));
      $turnstile_key = Key::load(self::TURNSTILE_KEY_ID);
      $turnstile_key_values = $turnstile_key ? ($turnstile_key->getKeyValues() ?: []) : [];
      $has_stored_secret = !empty($turnstile_key_values['secret_key']);
      if ($site_key === '') {
        $form_state->setErrorByName('turnstile_site_key', $this->t('Enter a site key before selecting Cloudflare Turnstile as the backend.'));
      }
      if ($secret_key === '' && !$has_stored_secret) {
        $form_state->setErrorByName('turnstile_secret_key', $this->t('Enter a secret key before selecting Cloudflare Turnstile as the backend.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $backend = $form_state->getValue('backend');

    switch ($backend) {
      case 'none':
        $this->setManagedCaptchaPointsStatus(FALSE);
        break;

      case 'basic':
        $this->configFactory()->getEditable(self::CAPTCHA_SETTINGS)
          ->set('default_challenge', 'captcha/Math')
          ->save();
        $this->setManagedCaptchaPointsStatus(TRUE);
        break;

      case 'recaptcha':
        $this->saveRecaptchaCredentials($form_state);
        $this->configFactory()->getEditable(self::CAPTCHA_SETTINGS)
          ->set('default_challenge', 'recaptcha/reCAPTCHA')
          ->save();
        $this->setManagedCaptchaPointsStatus(TRUE);
        break;

      case 'turnstile':
        $this->saveTurnstileCredentials($form_state);
        $this->configFactory()->getEditable(self::CAPTCHA_SETTINGS)
          ->set('default_challenge', 'turnstile/Turnstile')
          ->save();
        $this->setManagedCaptchaPointsStatus(TRUE);
        break;

      case 'altcha':
        $this->configFactory()->getEditable(self::CAPTCHA_SETTINGS)
          ->set('default_challenge', 'altcha/ALTCHA')
          ->save();
        $this->setManagedCaptchaPointsStatus(TRUE);
        break;
    }

    $this->setHoneypotProtectionStatus((bool) $form_state->getValue('honeypot'));

    $this->messenger()->addStatus($this->t('The bot protection configuration has been saved.'));
  }

  /**
   * Enables or disables the CAPTCHA points Mukurtu manages by default.
   */
  protected function setManagedCaptchaPointsStatus(bool $enabled): void {
    foreach (self::MANAGED_CAPTCHA_POINTS as $id) {
      $point = CaptchaPoint::load($id);
      if ($point) {
        $point->setStatus($enabled)->save();
      }
    }
  }

  /**
   * Enables or disables Honeypot protection on the forms Mukurtu manages.
   */
  protected function setHoneypotProtectionStatus(bool $enabled): void {
    $config = $this->configFactory()->getEditable(self::HONEYPOT_SETTINGS);
    $form_settings = $config->get('form_settings') ?? [];
    foreach (self::MANAGED_CAPTCHA_POINTS as $id) {
      $form_settings[$id] = $enabled;
    }
    $config->set('form_settings', $form_settings)->save();
  }

  /**
   * Saves reCAPTCHA credentials submitted with the main form.
   */
  protected function saveRecaptchaCredentials(FormStateInterface $form_state): void {
    $site_key = trim((string) $form_state->getValue('recaptcha_site_key'));
    $secret_key = trim((string) $form_state->getValue('recaptcha_secret_key'));
    $config = $this->configFactory()->getEditable(self::RECAPTCHA_SETTINGS);
    $stored_secret = $config->get('secret_key');

    if ($site_key !== '') {
      $config->set('site_key', $site_key);
    }
    // Leave the stored secret untouched if the submitted value is exactly
    // the masked placeholder shown for it (i.e. the admin didn't change it).
    if ($secret_key !== '' && $secret_key !== ($stored_secret ? $this->maskSecret($stored_secret) : NULL)) {
      $config->set('secret_key', $secret_key);
    }
    $config->save();
  }

  /**
   * Submit handler that clears stored reCAPTCHA credentials.
   */
  public function clearRecaptchaCredentials(array &$form, FormStateInterface $form_state): void {
    $this->configFactory()->getEditable(self::RECAPTCHA_SETTINGS)
      ->set('site_key', '')
      ->set('secret_key', '')
      ->save();
    $this->messenger()->addStatus($this->t('The reCAPTCHA credentials have been cleared.'));
    $form_state->setRebuild();
  }

  /**
   * Saves Turnstile credentials submitted with the main form as a Key entity.
   */
  protected function saveTurnstileCredentials(FormStateInterface $form_state): void {
    $site_key = trim((string) $form_state->getValue('turnstile_site_key'));
    $secret_key = trim((string) $form_state->getValue('turnstile_secret_key'));

    if ($site_key === '' && $secret_key === '') {
      return;
    }

    $key = Key::load(self::TURNSTILE_KEY_ID);
    $existing_values = $key ? ($key->getKeyValues() ?: []) : [];
    $stored_secret = $existing_values['secret_key'] ?? '';

    $new_values = [
      'site_key' => $site_key !== '' ? $site_key : ($existing_values['site_key'] ?? ''),
      // Leave the stored secret untouched if the submitted value is exactly
      // the masked placeholder shown for it.
      'secret_key' => ($secret_key !== '' && $secret_key !== ($stored_secret !== '' ? $this->maskSecret($stored_secret) : NULL))
        ? $secret_key
        : $stored_secret,
    ];

    if (!$key) {
      $key = Key::create([
        'id' => self::TURNSTILE_KEY_ID,
        'label' => 'Mukurtu Turnstile (Cloudflare) Keys',
        'key_type' => 'authentication_multivalue',
        'key_type_settings' => [],
        'key_provider' => 'config',
        'key_provider_settings' => ['base64_encoded' => FALSE],
        'key_input' => 'none',
      ]);
    }
    $key->setKeyValue(json_encode($new_values));
    $key->save();

    $this->configFactory()->getEditable(self::TURNSTILE_SETTINGS)
      ->set('keys', self::TURNSTILE_KEY_ID)
      ->save();
  }

  /**
   * Submit handler that clears the stored Turnstile Key entity.
   */
  public function clearTurnstileCredentials(array &$form, FormStateInterface $form_state): void {
    if ($key = Key::load(self::TURNSTILE_KEY_ID)) {
      $key->delete();
    }
    $this->messenger()->addStatus($this->t('The Cloudflare Turnstile credentials have been cleared.'));
    $form_state->setRebuild();
  }

}
