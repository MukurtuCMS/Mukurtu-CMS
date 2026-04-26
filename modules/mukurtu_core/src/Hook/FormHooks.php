<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Render\Element;
use Drupal\og\Og;
use Drupal\user\Entity\User;

/**
 * Hook implementations for mukurtu_core forms.
 */
class FormHooks {

  /**
   * Implements hook_form_FORM_ID_alter() for 'language_content_settings_form'.
   *
   * Hides og_group fields from the translation settings form to prevent users
   * from marking og_group bundle fields as translatable.
   *
   * This hook must run after the content_translation module's form alter hook
   * (\Drupal\content_translation\Hook\ContentTranslationHooks::formLanguageContentSettingsFormAlter)
   * which adds the field translation checkboxes via
   * _content_translation_form_language_content_settings_form_alter().
   */
  #[Hook('form_language_content_settings_form_alter', order: new OrderAfter(['content_translation']))]
  public function formLanguageContentSettingsFormAlter(array &$form, FormStateInterface $form_state): void {
    // Check if the settings array exists.
    if (empty($form['settings'])) {
      return;
    }

    // Loop through all entity types in the form.
    foreach (Element::children($form['settings']) as $entity_type_id) {
      // Loop through all bundles for this entity type.
      foreach (Element::children($form['settings'][$entity_type_id]) as $bundle) {
        if (empty($form['settings'][$entity_type_id][$bundle]['fields'])) {
          continue;
        }
        // Loop through all fields for this bundle.
        foreach (Element::children($form['settings'][$entity_type_id][$bundle]['fields']) as $field_name) {
          // Hide the og_group field from translation settings.
          if ($field_name !== 'og_group') {
            continue;
          }
          unset($form['settings'][$entity_type_id][$bundle]['fields'][$field_name]);

          // Also hide any column settings for the og_group field if they exist.
          if (isset($form['settings'][$entity_type_id][$bundle]['columns'][$field_name])) {
            unset($form['settings'][$entity_type_id][$bundle]['columns'][$field_name]);
          }
        }
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'user-register-form' and
   * 'user-form'.
   *
   * Hides 'Administrator' option from the Roles selection for Mukurtu Managers
   * so that they cannot assign the admin role.
   */
  #[Hook('form_user_register_form_alter')]
  public function formUserRegisterFormAlter(array &$form, FormStateInterface $form_state): void {
    $currentUser = \Drupal::currentUser()->getAccount();
    /** @var \Drupal\Core\Session\UserSession $currentUser */
    if ($currentUser->hasRole('mukurtu_manager')) {
      if (isset($form['account']['roles']['#options']['administrator'])) {
        unset($form['account']['roles']['#options']['administrator']);
      }
    }

    // Move display name field to sit directly below password in the account group.
    if (isset($form['account']['pass'])) {
      $form['account']['pass']['#weight'] = 0.0012;
    }
    if (isset($form['field_display_name'])) {
      $form['account']['field_display_name'] = $form['field_display_name'];
      $form['account']['field_display_name']['#weight'] = 0.0013;
      unset($form['field_display_name']);
    }

    // The notify section is only useful to authenticated users with create
    // access; skip it for anonymous self-registration.
    $currentAccount = \Drupal::currentUser();
    if ($currentAccount->isAnonymous()) {
      return;
    }

    $entityTypeManager = \Drupal::entityTypeManager();
    $protocolStorage = $entityTypeManager->getStorage('protocol');

    // Site admins see all communities/protocols. Other privileged users (e.g.
    // Mukurtu Managers) are limited to communities they actively manage so
    // that community/protocol names aren't exposed beyond their membership.
    if ($currentAccount->hasPermission('administer users')) {
      $communityEntities = $entityTypeManager->getStorage('community')->loadMultiple();
    }
    else {
      $userEntity = User::load($currentAccount->id());
      $memberships = array_filter(Og::getMemberships($userEntity), fn($m) => $m->getGroupBundle() === 'community');
      $managerMemberships = array_filter($memberships, fn($m) => $m->hasPermission('manage members'));
      $communityEntities = array_filter(array_map(fn($m) => $m->getGroup(), $managerMemberships));
    }

    $communityOptions = [];
    $protocolsByCommunity = [];
    foreach ($communityEntities as $community) {
      $communityOptions[$community->id()] = $community->getName();
      $communityProtocolIds = $protocolStorage->getQuery()
        ->condition('field_communities', $community->id())
        ->accessCheck(FALSE)
        ->execute();
      $communityProtocols = [];
      foreach ($protocolStorage->loadMultiple($communityProtocolIds) as $protocol) {
        $communityProtocols[$protocol->id()] = $protocol->getName();
      }
      asort($communityProtocols);
      if (!empty($communityProtocols)) {
        $protocolsByCommunity[$community->id()] = [
          'label' => $community->getName(),
          'protocols' => $communityProtocols,
        ];
      }
    }
    asort($communityOptions);
    uasort($protocolsByCommunity, fn($a, $b) => strcmp($a['label'], $b['label']));

    $form['notify'] = [
      '#type' => 'details',
      '#title' => t('Notify other users of new account'),
      '#description' => t('Optionally send a notification to other users when the account is created.'),
      '#open' => FALSE,
      '#attached' => ['library' => ['mukurtu_core/notify-form']],
    ];

    $form['notify']['notify_all_managers'] = [
      '#type' => 'checkbox',
      '#title' => t('Notify all Mukurtu Managers'),
      '#default_value' => FALSE,
    ];

    if (!empty($communityOptions)) {
      $form['notify']['notify_communities'] = [
        '#type' => 'checkboxes',
        '#title' => t('Notify all community managers in the following communities:'),
        '#options' => $communityOptions,
        '#required' => FALSE,
        '#attributes' => ['class' => ['notify-form-checkboxes']],
      ];
    }

    if (!empty($protocolsByCommunity)) {
      $protocols_label_id = 'notify-protocols-label';
      $form['notify']['notify_protocols'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['notify-protocols-wrapper'],
          'role' => 'group',
          'aria-labelledby' => $protocols_label_id,
        ],
      ];
      $form['notify']['notify_protocols']['title'] = [
        '#markup' => '<p id="' . $protocols_label_id . '" class="fieldset__label fieldset__label--group">' . t('Notify all protocol stewards in the following protocols:') . '</p>',
      ];

      foreach ($protocolsByCommunity as $communityId => $data) {
        $form['notify']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
          '#attributes' => ['class' => ['notify-form-checkboxes']],
        ];
      }
    }

    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;
    $users_label_id = 'notify-users-label';

    $form['notify']['notify_users'] = [
      '#type' => 'container',
      '#prefix' => '<div id="notify-users-wrapper" role="group" aria-labelledby="' . $users_label_id . '" aria-live="polite"><p id="' . $users_label_id . '" class="fieldset__label fieldset__label--group">' . t('Notify specific users:') . '</p>',
      '#suffix' => '</div>',
    ];

    for ($i = 0; $i < $notifyUserCount; $i++) {
      $form['notify']['notify_users']['user_' . $i] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => 'user',
        // Numbered labels give screen reader users positional context when
        // multiple fields exist.
        '#title' => t('User @num', ['@num' => $i + 1]),
        '#title_display' => 'invisible',
        '#selection_handler' => 'mukurtu_manager_users',
        '#required' => FALSE,
      ];
    }

    $form['notify']['notify_users']['add_more'] = [
      '#type' => 'submit',
      '#value' => t('Add another user'),
      '#submit' => [[self::class, 'addMoreNotifyUser']],
      '#ajax' => [
        'callback' => [self::class, 'addMoreNotifyUserCallback'],
        'wrapper' => 'notify-users-wrapper',
      ],
      '#limit_validation_errors' => [],
    ];

    $form['actions']['submit']['#submit'][] = [self::class, 'userRegisterNotifySubmit'];
  }

  /**
   * Submit handler that sends notifications after a new user account is created.
   */
  public static function userRegisterNotifySubmit(array &$form, FormStateInterface $form_state): void {
    $notifyUids = mukurtu_notifications_extract_notify_uids($form_state);
    if (!empty($notifyUids)) {
      $new_user = $form_state->getFormObject()->getEntity();
      mukurtu_notifications_notify_new_account_created($new_user, $notifyUids);
    }
  }

  /**
   * AJAX submit handler to add another user autocomplete field.
   */
  public static function addMoreNotifyUser(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('notify_user_count') ?? 1;
    $form_state->set('notify_user_count', $count + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback to return the updated notify_users container.
   */
  public static function addMoreNotifyUserCallback(array &$form, FormStateInterface $form_state): array {
    return $form['notify']['notify_users'];
  }

  /**
   * Implements hook_form_FORM_ID_alter() for 'user-form'.
   *
   * Hides 'Administrator' option from the Roles selection for Mukurtu Managers
   * so that they cannot assign the admin role.
   */
  #[Hook('form_user_form_alter')]
  public function formUserFormAlter(array &$form, FormStateInterface $form_state): void {
    $currentUser = \Drupal::currentUser()->getAccount();
    /** @var \Drupal\Core\Session\UserSession $currentUser */
    if ($currentUser->hasRole('mukurtu_manager')) {
      if (isset($form['account']['roles']['#options']['administrator'])) {
        unset($form['account']['roles']['#options']['administrator']);
      }
    }

    // Move display name field to sit directly below password in the account group.
    if (isset($form['account']['pass'])) {
      $form['account']['pass']['#weight'] = 0.0012;
    }
    if (isset($form['field_display_name'])) {
      $form['account']['field_display_name'] = $form['field_display_name'];
      $form['account']['field_display_name']['#weight'] = 0.0013;
      unset($form['field_display_name']);
    }
  }

  /**
   * Removes message_digest notification actions from the user admin bulk form.
   *
   * These come from message_digest_ui optional config and should not be
   * exposed in Mukurtu's user management UI.
   */
  #[Hook('form_alter')]
  public function formAlterRemoveNotificationBulkActions(array &$form, FormStateInterface $form_state, string $form_id): void {
    if (!str_starts_with($form_id, 'views_form_user_admin_people_') &&
        !str_starts_with($form_id, 'views_form_mukurtu_people_')) {
      return;
    }

    $actions_to_remove = [
      'message_digest_interval.email_user.immediate',
      'message_digest_interval.email_user.daily',
      'message_digest_interval.email_user.weekly',
    ];

    if (isset($form['header']['user_bulk_form']['action']['#options'])) {
      foreach ($actions_to_remove as $action_id) {
        unset($form['header']['user_bulk_form']['action']['#options'][$action_id]);
      }
    }
  }

}
