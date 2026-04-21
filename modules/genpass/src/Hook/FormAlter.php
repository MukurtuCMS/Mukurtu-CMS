<?php

declare(strict_types=1);

namespace Drupal\genpass\Hook;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\genpass\GenpassInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Alter forms to include this modules configured changes to password entry.
 */
class FormAlter {

  use DependencySerializationTrait;
  use StringTranslationTrait;

  /**
   * Static cached version of user forms config.
   *
   * @var array|null
   */
  protected $userFormsInfo = NULL;

  /**
   * Constructs a new FormAlter object.
   */
  public function __construct(
    protected AccountInterface $currentUser,
    #[Autowire(service: 'cache.default')]
    protected CacheBackendInterface $cacheDefault,
    protected MessengerInterface $messenger,
    protected ModuleHandlerInterface $moduleHandler,
    protected PasswordGeneratorInterface $passwordGenerator,
    protected RouteMatchInterface $routeMatch,
    TranslationInterface $string_translation,
  ) {
    $this->stringTranslation = $string_translation;
  }

  /**
   * Alter configured forms to adjust password and notify fields.
   */
  #[Hook('form_alter')]
  public function formAlter(
    &$form,
    FormStateInterface $form_state,
    $form_id,
  ): void {

    // Load user forms config on first run.
    if (is_null($this->userFormsInfo)) {
      $this->loadUserFormsInfo();
    }

    // Only continue if the form_id is configured to alter.
    if (!isset($this->userFormsInfo[$form_id])) {
      return;
    }

    // Settings for this form id.
    $form_settings = $this->userFormsInfo[$form_id];

    // Password field must exist on this form for it to be altered. This can
    // be missing when "Require email verification when a visitor creates an
    // account" is enabled in account settings.
    $pass_item =& $this->findFormItem(
      $form,
      $form_settings['genpass_password_field']
    );
    if (empty($pass_item)) {
      return;
    }

    // Determine if the current user in an admin.
    $is_admin = $this->currentUser->hasPermission('administer users');

    // Existing entities can always access the password field unless user is an
    // admin, and they are not looking at their own. This is only run when the
    // form is of EntityFormInterface.
    $form_object = $form_state->getFormObject();
    if ($form_object instanceof EntityFormInterface) {

      // Entity of type user is assumed here. Patches welcome to deal with other
      // entity types if that ever comes to pass.
      $form_user = $form_object->getEntity();
      if ($form_user->id()) {

        // If admin is not looking at their own entity, hide the field.
        if ($is_admin && $this->currentUser->id() != $form_user->id()) {
          if ($form_settings['genpass_admin_mode'] == GenpassInterface::PASSWORD_ADMIN_HIDE) {
            $pass_item['#access'] = FALSE;
            $pass_item['#required'] = FALSE;
          }
        }

        // No further changes are made on existing entities.
        return;
      }
    }

    // Add validation function, where password may get set.
    $form['#validate'][] = [$this, 'genpassRegisterValidate'];

    // Pass settings to validation function.
    foreach ($form_settings as $variable => $value) {
      $form[$variable] = [
        '#type' => 'value',
        '#value' => $value,
      ];
    }

    // Set password field mode - use admin settings if an admin.
    $mode = $form_settings['genpass_mode'];
    if ($is_admin) {
      $mode = $form_settings['genpass_admin_mode'];

      // Help avoid obvious consequence of password being optional.
      $notify_item =& $this->findFormItem(
        $form,
        $form_settings['genpass_notify_item']
      );
      if (!empty($notify_item)) {
        $notify_item['#description'] = $this->t('This is recommended when auto-generating the password; otherwise, neither you nor the new user will know the password.');
      }
    }

    switch ($mode) {
      // If password is optional, don't require it, and give the user an
      // indication of what will happen if left blank.
      case GenpassInterface::PASSWORD_OPTIONAL:
        $pass_item['#description'] = implode(' ', [
          $pass_item['#description'] ?? '',
          $this->t('If left blank, a password will be generated for you.'),
        ]);
        $pass_item['#required'] = FALSE;
        break;

      // If password is restricted, remove access.
      case GenpassInterface::PASSWORD_RESTRICTED:
        $pass_item['#access'] = FALSE;
        $pass_item['#required'] = FALSE;
        break;
    }
  }

  /**
   * Build the user forms info.
   */
  #[Hook('rebuild')]
  public function buildInfo(): array {

    // Rebuild as not present in cache or reset.
    $build_genpass_user_forms = $this->moduleHandler
      ->invokeAll('genpass_user_forms');

    // Allow other modules to then alter the initial values.
    $this->moduleHandler
      ->alter('genpass_user_forms', $build_genpass_user_forms);

    // Rebuild the entire form ids and settings array; keep only known settings.
    $genpass_user_forms = $this
      ->sanitiseUserFormsInfo($build_genpass_user_forms);

    // Add the data to cache.
    $tags = ['genpass'];
    $this->cacheDefault->set(
      'genpass:user_forms',
      $genpass_user_forms,
      CacheBackendInterface::CACHE_PERMANENT,
      $tags
    );

    return $this->userFormsInfo = $genpass_user_forms;
  }

  /**
   * Load user forms info from a cache or rebuild it.
   *
   * @param bool $reset
   *   Ignore cache and rebuild info from hook call.
   */
  public function loadUserFormsInfo(bool $reset = FALSE): void {
    if (!$reset && !is_null($this->userFormsInfo)) {
      return;
    }

    // Get pre-calculated form ids and settings from cache. Use default cache
    // because this is not critical enough to be included in discovery.
    $cid = 'genpass:user_forms';
    if (!$reset && ($item = $this->cacheDefault->get($cid, FALSE)) !== FALSE) {
      // Set the static cached form ids data.
      $this->userFormsInfo = $item->data;
      return;
    }

    // Was not in cache. Rebuild.
    $this->buildInfo();
  }

  /**
   * Sanitise the user forms info obtained from genpass_user_forms hook.
   *
   * @param array $build_genpass_user_forms
   *   The original unprocessed array obtained from genpass_user_forms hook.
   *
   * @return array
   *   The sanitised array.
   */
  protected function sanitiseUserFormsInfo(
    array $build_genpass_user_forms,
  ): array {
    $genpass_user_forms = [];

    // Required settings and default values to include in every entry.
    $interface_vars = ['genpass_mode', 'genpass_admin_mode', 'genpass_display'];
    $array_vars = [
      'genpass_password_field' => [
        ['account', 'pass'],
        ['pass'],
      ],
      'genpass_notify_item' => [
        ['account', 'notify'],
        ['notify'],
      ],
    ];

    // Ensure every form id in list has the required settings or defaults.
    foreach ($build_genpass_user_forms as $form_id => $form_settings) {
      foreach ($interface_vars as $variable) {
        // The -1 is a flag so that the function returns the corrected default.
        $genpass_user_forms[$form_id][$variable] = $this->sanitiseInterfaceValue(
          $variable,
          $form_settings[$variable],
          -1
        );
      }

      // Add default password field and notify item if missing.
      foreach ($array_vars as $var => $default) {
        if (!empty($form_settings[$var]) && is_array($form_settings[$var])) {
          $genpass_user_forms[$form_id][$var] = $form_settings[$var];
        }
        else {
          $genpass_user_forms[$form_id][$var] = $default;
        }
      }
    }

    return $genpass_user_forms;
  }

  /**
   * Return valid interface value or given default if the value is valid.
   *
   * @param string $variable
   *   One of genpass_mode, genpass_admin_mode, or genpass_display.
   * @param int $value
   *   Value to check and maybe return.
   * @param int $default
   *   Value to return if value above is invalid or not set.
   *
   * @return int
   *   The given value, valid default, or fallback known good value.
   *
   * @throws \InvalidArgumentException
   *   If the given variable name is not valid.
   */
  protected function sanitiseInterfaceValue(
    string $variable,
    int $value,
    int $default,
  ): int {

    switch ($variable) {
      case 'genpass_mode':
        switch ($value) {
          case GenpassInterface::PASSWORD_REQUIRED:
          case GenpassInterface::PASSWORD_OPTIONAL:
          case GenpassInterface::PASSWORD_RESTRICTED:
            return $value;

          default:
            // Recurse to self to ensure default is also valid.
            return $this->sanitiseInterfaceValue(
              $variable,
              $default,
              GenpassInterface::PASSWORD_RESTRICTED
            );
        }
        break;

      case 'genpass_admin_mode':
        switch ($value) {
          case GenpassInterface::PASSWORD_ADMIN_SHOW:
          case GenpassInterface::PASSWORD_ADMIN_HIDE:
            return $value;

          default:
            // Recurse to self to ensure default is also valid.
            return $this->sanitiseInterfaceValue(
              $variable,
              $default,
              GenpassInterface::PASSWORD_ADMIN_SHOW
            );
        }
        break;

      case 'genpass_display':
        switch ($value) {
          case GenpassInterface::PASSWORD_DISPLAY_NONE:
          case GenpassInterface::PASSWORD_DISPLAY_ADMIN:
          case GenpassInterface::PASSWORD_DISPLAY_USER:
          case GenpassInterface::PASSWORD_DISPLAY_BOTH:
            return $value;

          default:
            // Recurse to self to ensure default is also valid.
            return $this->sanitiseInterfaceValue(
              $variable,
              $default,
              GenpassInterface::PASSWORD_DISPLAY_NONE
            );
        }
        break;

      default:
        // An invalid variable is a fatal developer issue.
        throw new \InvalidArgumentException('Unknown variable: ' . $variable);
    }
  }

  /**
   * Helper function to find an item in the entity form.
   *
   * Location many vary based on profile module, or 3rd party module providing a
   * new location or field name.
   *
   * @param array $form
   *   The form array.
   * @param array $array_parents
   *   An array of parents arrays to try.
   *
   * @return array|null
   *   The located form item (by reference), or NULL if not found.
   */
  protected function &findFormItem(
    array &$form,
    array $array_parents,
  ): ?array {
    // As this function returns a reference, what is returned must be a
    // variable.
    $form_item = NULL;

    foreach ($array_parents as $parents) {
      // If this is just a string, then function has been called with a single
      // array instead of an array of arrays. Wrap in array and call again.
      if (!is_array($parents)) {
        return $this->findFormItem($form, [$array_parents]);
      }

      // Check this parent and return if found.
      $exists = FALSE;
      $form_item =& NestedArray::getValue($form, $parents, $exists);
      if ($exists) {
        return $form_item;
      }
    }

    // No item found at any of the parent locations.
    return $form_item;
  }

  /**
   * User registration validation callback.
   */
  public function genpassRegisterValidate(
    $form,
    FormStateInterface $form_state,
  ): void {
    // Only validate on final submission, and when there are no errors.
    if ($form_state->getErrors() || !$form_state->isSubmitted()) {
      return;
    }

    // Generate password when one hasn't been provided.
    if (empty($form_state->getValue('pass'))) {

      // Generate and set password.
      $pass = $this->passwordGenerator->generate();
      $password_location = $form_state->getValue('genpass_password_field');
      $pass_item =& $this->findFormItem($form, $password_location);
      $form_state->setValueForElement($pass_item, $pass);

      $display = $form_state->getValue('genpass_display');
      $is_admin_or_both = in_array($display, [
        GenpassInterface::PASSWORD_DISPLAY_ADMIN,
        GenpassInterface::PASSWORD_DISPLAY_BOTH,
      ]);
      $is_user_or_both = in_array($display, [
        GenpassInterface::PASSWORD_DISPLAY_USER,
        GenpassInterface::PASSWORD_DISPLAY_BOTH,
      ]);

      $genpass_mode = $form_state->getValue('genpass_mode');
      $genpass_admin_mode = $form_state->getValue('genpass_admin_mode');

      // Keep messages as original objects to pass HTML through messenger.
      $messages = [];

      // Administrator created the user.
      if ($this->routeMatch->getRouteName() == 'user.admin_create') {
        if ($genpass_admin_mode == GenpassInterface::PASSWORD_ADMIN_SHOW) {
          $messages[] = $this->t('Since you did not provide a password, it was generated automatically for this account.');
        }
        if ($is_admin_or_both) {
          $messages[] = $this->t('The password is: <strong class="genpass-password nowrap">@password</strong>', ['@password' => $pass]);
        }
      }
      // Optional - User did not provide password, so it was generated.
      elseif ($genpass_mode == GenpassInterface::PASSWORD_OPTIONAL) {
        $messages[] = $this->t('Since you did not provide a password, it was generated for you.');
        if ($is_user_or_both) {
          $messages[] = $this->t('Your password is: <strong class="genpass-password nowrap">@password</strong>', ['@password' => $pass]);
        }
      }
      // Restricted - User was forced to receive a generated password.
      elseif ($genpass_mode == GenpassInterface::PASSWORD_RESTRICTED) {
        if ($is_user_or_both) {
          $messages[] = $this->t('The following password was generated for you: <strong class="genpass-password nowrap">@password</strong>', ['@password' => $pass]);
        }
      }

      if (!empty($messages)) {
        foreach ($messages as $message) {
          $this->messenger->addStatus($message);
        }
      }
    }
  }

}
