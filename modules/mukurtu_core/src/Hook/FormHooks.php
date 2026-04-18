<?php

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Hook\Order\OrderAfter;
use Drupal\Core\Render\Element;

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

    // Build community options and protocol options grouped by community (site-wide).
    $communityOptions = [];
    $protocolsByCommunity = [];
    $entityTypeManager = \Drupal::entityTypeManager();
    $protocolStorage = $entityTypeManager->getStorage('protocol');
    $communityEntities = $entityTypeManager->getStorage('community')->loadMultiple();
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
        // Use the visible legend rendered by #type => 'checkboxes' as the
        // group label so it is properly associated via <fieldset>/<legend>.
        '#title' => t('Notify all community managers in the following communities:'),
        '#options' => $communityOptions,
        '#required' => FALSE,
        '#attributes' => ['class' => ['notify-form-checkboxes'], 'style' => 'margin-inline-start: .5rem;'],
      ];
    }

    if (!empty($protocolsByCommunity)) {
      $form['notify']['notify_protocols'] = [
        '#type' => 'container',
        '#attributes' => ['class' => ['notify-protocols-wrapper']],
      ];
      $form['notify']['notify_protocols']['title'] = [
        // Use <p> instead of <label> — this text labels the group visually
        // but is not associated with a specific control.
        '#markup' => '<p class="fieldset__label fieldset__label--group">' . t('Notify all protocol stewards in the following protocols:') . '</p>',
      ];

      foreach ($protocolsByCommunity as $communityId => $data) {
        $form['notify']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
          '#attributes' => ['class' => ['notify-form-checkboxes'], 'style' => 'margin-inline-start: .5rem;'],
        ];
      }
    }

    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;

    $form['notify']['notify_users'] = [
      '#type' => 'container',
      // aria-live="polite" announces new fields to screen readers when
      // "Add another user" fires. <p> replaces the unassociated <label>.
      '#prefix' => '<div id="notify-users-wrapper" aria-live="polite"><p class="fieldset__label fieldset__label--group">' . t('Notify specific users:') . '</p>',
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
    // Resolve individual user selections from AJAX "Add another" fields.
    $notifyUids = [];
    $notifyUserCount = $form_state->get('notify_user_count') ?? 1;
    for ($i = 0; $i < $notifyUserCount; $i++) {
      $uid = $form_state->getValue(['notify_users', 'user_' . $i]);
      if (!empty($uid)) {
        $notifyUids[] = (int) $uid;
      }
    }

    // Resolve group selections.
    $allManagers = (bool) $form_state->getValue('notify_all_managers');
    $communityIds = array_keys(array_filter($form_state->getValue('notify_communities') ?? []));
    $protocolIds = [];
    foreach ($form_state->getValue('notify_protocols') ?? [] as $groupValues) {
      if (is_array($groupValues)) {
        $protocolIds = array_merge($protocolIds, array_keys(array_filter($groupValues)));
      }
    }
    $protocolIds = array_unique($protocolIds);

    if (function_exists('mukurtu_notifications_resolve_notify_groups')) {
      $groupUids = mukurtu_notifications_resolve_notify_groups($allManagers, $communityIds, $protocolIds);
      $notifyUids = array_unique(array_merge($notifyUids, $groupUids));
    }

    if (!empty($notifyUids) && function_exists('mukurtu_notifications_notify_new_account_created')) {
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
}
