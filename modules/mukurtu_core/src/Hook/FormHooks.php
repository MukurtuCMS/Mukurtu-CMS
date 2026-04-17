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

    // Move display name field to sit directly below username in the account group.
    if (isset($form['field_display_name'])) {
      $form['account']['field_display_name'] = $form['field_display_name'];
      $form['account']['field_display_name']['#weight'] = 0.0015;
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
    ];

    $form['notify']['notify_all_managers'] = [
      '#type' => 'checkbox',
      '#title' => t('Notify all Mukurtu Managers'),
      '#default_value' => FALSE,
    ];

    if (!empty($communityOptions)) {
      $form['notify']['notify_communities'] = [
        '#type' => 'checkboxes',
        '#title' => t('Notify all protocol stewards in the following protocols:'),
        '#options' => $communityOptions,
        '#required' => FALSE,
      ];
    }

    if (!empty($protocolsByCommunity)) {
      $form['notify']['notify_protocols'] = [
        '#type' => 'container',
        '#title' => t('Notify protocol stewards'),
        '#attributes' => ['class' => ['notify-protocols-wrapper']],
      ];
/*      $form['notify']['notify_protocols']['title'] = [
*        '#markup' => '<label>' . t('Notify all protocol stewards in the following protocols:') . '</label>',
*      ];
*/
foreach ($protocolsByCommunity as $communityId => $data) {
        $form['notify']['notify_protocols'][$communityId] = [
          '#type' => 'checkboxes',
          '#title' => $data['label'],
          '#options' => $data['protocols'],
        ];
      }
    }

    $form['notify']['notify_users'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#title' => t('Notify specific users:'),
      '#description' => t('Notify individual users by name. Separate multiple users with commas.'),
      '#tags' => TRUE,
      '#required' => FALSE,
      '#selection_handler' => 'mukurtu_manager_users',
    ];

    $form['actions']['submit']['#submit'][] = [self::class, 'userRegisterNotifySubmit'];
  }

  /**
   * Submit handler that sends notifications after a new user account is created.
   */
  public static function userRegisterNotifySubmit(array &$form, FormStateInterface $form_state): void {
    // Resolve individual user selections.
    $rawNotifyUsers = $form_state->getValue('notify_users') ?? [];
    $notifyUids = array_column($rawNotifyUsers, 'target_id');

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
  }
}
