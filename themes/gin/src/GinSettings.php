<?php

namespace Drupal\gin;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserDataInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

include_once __DIR__ . '/../gin.theme';
_gin_include_theme_includes();

/**
 * Service to handle overridden user settings.
 */
class GinSettings implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Settings constructor.
   *
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   * @param \Drupal\user\UserDataInterface|null $userData
   *   The user data service.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected ?UserDataInterface $userData,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('config.factory'),
      $container->get('user.data', ContainerInterface::NULL_ON_INVALID_REFERENCE)
    );
  }

  /**
   * Get the setting for the current user.
   *
   * @param string $name
   *   The name of the setting.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object. Current user if NULL.
   *
   * @return array|bool|mixed|null
   *   The current value.
   */
  public function get($name, ?AccountInterface $account = NULL) {
    $value = NULL;
    if (!$account) {
      $account = $this->currentUser;
    }
    if ($this->userOverrideEnabled($account)) {
      $settings = $this->userData->get('gin', $account->id(), 'settings');
      if (isset($settings[$name])) {
        $value = $settings[$name];
      }
      else {
        // Try loading legacy settings from user data.
        $value = $this->userData->get('gin', $account->id(), $name);
      }
    }
    if (is_null($value)) {
      $admin_theme = $this->getAdminTheme();
      $value = theme_get_setting($name, $admin_theme);
    }
    return $value;
  }

  /**
   * Get the default setting from theme.
   *
   * @param string $name
   *   The name of the setting.
   *
   * @return array|bool|mixed|null
   *   The current value.
   */
  public function getDefault($name) {
    $admin_theme = $this->getAdminTheme();
    return theme_get_setting($name, $admin_theme);
  }

  /**
   * Set user overrides.
   *
   * @param array $settings
   *   The user specific theme settings.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object. Current user if NULL.
   */
  public function setAll(array $settings, ?AccountInterface $account = NULL) {
    if (!$account || !$this->userData) {
      $account = $this->currentUser;
    }
    // All settings are deleted to remove legacy settings.
    $this->userData->delete('gin', $account->id());
    $this->userData->set('gin', $account->id(), 'enable_user_settings', TRUE);
    $this->userData->set('gin', $account->id(), 'settings', $settings);
  }

  /**
   * Clears all gin settings for the current user.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object. Current user if NULL.
   */
  public function clear(?AccountInterface $account = NULL) {
    if (!$account || !$this->userData) {
      $account = $this->currentUser;
    }
    $this->userData->delete('gin', $account->id());
  }

  /**
   * Determine if user overrides are allowed.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function allowUserOverrides() {
    $admin_theme = $this->getAdminTheme();
    return theme_get_setting('show_user_theme_settings', $admin_theme);
  }

  /**
   * Determine if the user enabled overrides.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object. Current user if NULL.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function userOverrideEnabled(?AccountInterface $account = NULL) {
    $overrides = &drupal_static(__CLASS__ . '_' . __METHOD__, []);

    if (!$account || !$this->userData) {
      $account = $this->currentUser;
    }

    if (!isset($overrides[$account->id()])) {
      $overrides[$account->id()] = $this->allowUserOverrides()
        && (bool) $this->userData->get('gin', $account->id(), 'enable_user_settings');
    }

    return $overrides[$account->id()];
  }

  /**
   * Check if the user setting overrides the global setting.
   *
   * @param string $name
   *   Name of the setting to check.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object. Current user if NULL.
   *
   * @return bool
   *   TRUE or FALSE.
   */
  public function overridden($name, ?AccountInterface $account = NULL) {
    if (!$account) {
      $account = $this->currentUser;
    }
    $admin_theme = $this->getAdminTheme();
    return theme_get_setting($name, $admin_theme) !== $this->get($name, $account);
  }

  /**
   * Return the active admin theme.
   *
   * @return string
   *   The active admin theme name.
   */
  private function getAdminTheme() {
    $admin_theme = $this->configFactory->get('system.theme')->get('admin');
    if (empty($admin_theme)) {
      $admin_theme = $this->configFactory->get('system.theme')->get('default');
    }
    return $admin_theme;
  }

  /**
   * Build the settings form for the theme.
   *
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The account object.
   *
   * @return array
   *   The theme setting form elements.
   */
  public function getSettingsForm(?AccountInterface $account = NULL): array {
    $experimental_label = ' <span class="gin-experimental-flag">Experimental</span>';
    $beta_label = ' <span class="gin-beta-flag">Beta</span>';
    $new_label = ' <span class="gin-new-flag">New</span>';

    $form['enable_darkmode'] = [
      '#type' => 'radios',
      '#title' => $this->t('Appearance'),
      '#description' => $this->t('Enables Darkmode for the admin interface.'),
      '#default_value' => (string) ($account ? $this->get('enable_darkmode', $account) : $this->getDefault('enable_darkmode')),
      '#options' => [
        0 => $this->t('Light'),
        1 => $this->t('Dark'),
        'auto' => $this->t('Auto'),
      ],
    ];

    // Accent color setting.
    $form['preset_accent_color'] = [
      '#type' => 'radios',
      '#title' => $this->t('Accent color'),
      '#default_value' => $account ? $this->get('preset_accent_color', $account) : $this->getDefault('preset_accent_color'),
      '#options' => [
        'blue' => $this->t('Gin Blue (Default)'),
        'light_blue' => $this->t('Light Blue'),
        'dark_purple' => $this->t('Dark Purple'),
        'purple' => $this->t('Purple'),
        'teal' => $this->t('Teal'),
        'green' => $this->t('Green'),
        'pink' => $this->t('Pink'),
        'red' => $this->t('Red'),
        'orange' => $this->t('Orange'),
        'yellow' => $this->t('Yellow'),
        'neutral' => $this->t('Neutral'),
        'custom' => $this->t('Custom'),
      ],
      '#after_build' => [
        '_gin_accent_radios',
      ],
    ];

    // Accent color group.
    $form['accent_group'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Accent color'),
      '#description' => $this->t('Use with caution, values should meet a11y criteria.'),
      '#states' => [
        // Show if met.
        'visible' => [
          ':input[name="preset_accent_color"]' => ['value' => 'custom'],
        ],
      ],
    ];

    // Main Accent color setting.
    $form['accent_color'] = [
      '#type' => 'textfield',
      '#placeholder' => '#777777',
      '#maxlength' => 7,
      '#size' => 7,
      '#title' => $this->t('Custom Accent color'),
      '#title_display' => 'invisible',
      '#default_value' => $account ? $this->get('accent_color', $account) : $this->getDefault('accent_color'),
      '#group' => 'accent_group',
      '#attributes' => [
        'pattern' => '^#[a-fA-F0-9]{6}',
      ],
    ];

    // Accent color picker (helper field).
    $form['accent_group']['accent_picker'] = [
      '#type' => 'color',
      '#placeholder' => '#777777',
      '#default_value' => $account ? $this->get('accent_color', $account) : $this->getDefault('accent_color'),
      '#process' => [
        [static::class, 'processColorPicker'],
      ],
    ];

    // Focus color setting.
    $form['preset_focus_color'] = [
      '#type' => 'select',
      '#title' => $this->t('Focus color'),
      '#default_value' => $account ? $this->get('preset_focus_color', $account) : $this->getDefault('preset_focus_color'),
      '#options' => [
        'gin' => $this->t('Gin Focus color (Default)'),
        'green' => $this->t('Green'),
        'claro' => $this->t('Claro Green'),
        'orange' => $this->t('Orange'),
        'dark' => $this->t('Neutral'),
        'accent' => $this->t('Same as Accent color'),
        'custom' => $this->t('Custom'),
      ],
    ];

    // Focus color group.
    $form['focus_group'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom Focus color') . $beta_label,
      '#description' => $this->t('Use with caution, values should meet a11y criteria.'),
      '#states' => [
        // Show if met.
        'visible' => [
          ':input[name="preset_focus_color"]' => ['value' => 'custom'],
        ],
      ],
    ];

    // Focus color picker (helper).
    $form['focus_group']['focus_picker'] = [
      '#type' => 'color',
      '#placeholder' => '#777777',
      '#default_value' => $account ? $this->get('focus_color', $account) : $this->getDefault('focus_color'),
      '#process' => [
        [static::class, 'processColorPicker'],
      ],
    ];

    // Custom Focus color setting.
    $form['focus_color'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Focus color') . $beta_label,
      '#title_display' => 'invisible',
      '#placeholder' => '#777777',
      '#maxlength' => 7,
      '#size' => 7,
      '#default_value' => $account ? $this->get('focus_color', $account) : $this->getDefault('focus_color'),
      '#group' => 'focus_group',
      '#attributes' => [
        'pattern' => '^#[a-fA-F0-9]{6}',
      ],
    ];

    // High contrast mode.
    $form['high_contrast_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Increase contrast') . $experimental_label,
      '#description' => $this->t('Enables high contrast mode.'),
      '#default_value' => $account ? $this->get('high_contrast_mode', $account) : $this->getDefault('high_contrast_mode'),
    ];

    // Toolbar setting.
    $is_navigation_active = _gin_module_is_active('navigation');

    $form['classic_toolbar'] = [
      '#disabled' => $is_navigation_active,
      '#type' => 'radios',
      '#title' => $this->t('Navigation (Drupal Toolbar)'),
      '#default_value' => $account ? $this->get('classic_toolbar', $account) : $this->getDefault('classic_toolbar'),
      '#options' => [
        'new' => $this->t('New Drupal Navigation, Test integration') . $new_label . $experimental_label,
        'vertical' => $this->t('Sidebar, Vertical Toolbar (Default)'),
        'horizontal' => $this->t('Horizontal, Modern Toolbar'),
        'classic' => $this->t('Legacy, Classic Drupal Toolbar'),
      ],
      '#attributes' => $is_navigation_active ? ['class' => ['gin-core-navigation--is-active']] : [],
      '#description' => $is_navigation_active ? $this->t('This setting is currently deactivated as it is overwritten by the navigation module.') : '',
      '#after_build' => [
        '_gin_toolbar_radios',
      ],
    ];

    // Sticky action toggle.
    if (!_gin_module_is_active('navigation')) {
      $form['sticky_action_buttons'] = [
        '#type' => 'checkbox',
        '#title' => $this->t('Enable sticky action buttons') . $beta_label . $new_label,
        '#description' => $this->t('Displays all actions of the form in the sticky header.'),
        '#default_value' => $account ? $this->get('sticky_action_buttons', $account) : $this->getDefault('sticky_action_buttons'),
      ];
    }

    // Show secondary toolbar in Frontend.
    if (!_gin_module_is_active('navigation')) {
      if (!$account) {
        $form['secondary_toolbar_frontend'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Show Secondary Toolbar in Frontend'),
          '#description' => $this->t('Show the secondary toolbar in the Frontend (when logged in to Drupal).'),
          '#default_value' => $this->getDefault('secondary_toolbar_frontend'),
        ];
      }
    }

    // Layout density setting.
    $form['layout_density'] = [
      '#type' => 'radios',
      '#title' => $this->t('Layout density'),
      '#description' => $this->t('Changes the layout density for tables in the admin interface.'),
      '#default_value' => (string) ($account ? $this->get('layout_density', $account) : $this->getDefault('layout_density')),
      '#options' => [
        'default' => $this->t('Default'),
        'medium' => $this->t('Compact'),
        'small' => $this->t('Narrow'),
      ],
    ];

    // Description toggle.
    $form['show_description_toggle'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable form description toggle'),
      '#description' => $this->t('Show a help icon to show/hide form descriptions on content forms.'),
      '#default_value' => $account ? $this->get('show_description_toggle', $account) : $this->getDefault('show_description_toggle'),
    ];

    if (!$account) {
      foreach ($form as $key => $element) {
        $form[$key]['#after_build'][] = [
          GinAfterBuild::class, 'overriddenSettingByUser',
        ];
      }
    }

    return $form;
  }

  /**
   * Unset color picker fields.
   *
   * @param array $element
   *   An associative array containing the properties and children of the
   *   element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form element.
   */
  public static function processColorPicker(array $element, FormStateInterface $form_state) {
    $keys = $form_state->getCleanValueKeys();
    $form_state->setCleanValueKeys(array_merge((array) $keys, $element['#parents']));

    return $element;
  }

}
