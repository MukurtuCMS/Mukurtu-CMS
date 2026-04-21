<?php

namespace Drupal\token\Hook;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Form hooks for token module.
 */
final class TokenFormHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ModuleHandlerInterface $moduleHandler,
    protected readonly RendererInterface $renderer,
  ) {
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_block_form_alter')]
  public function formBlockFormAlter(&$form, FormStateInterface $form_state): void {
    $token_tree = [
      '#theme' => 'token_tree_link',
      '#token_types' => [],
    ];
    $rendered_token_tree = $this->renderer->render($token_tree);
    $form['settings']['label']['#description'] = $this->t('This field supports tokens. @browse_tokens_link', [
      '@browse_tokens_link' => $rendered_token_tree,
    ]);
    $form['settings']['label']['#element_validate'][] = 'token_element_validate';
    $form['settings']['label'] += ['#token_types' => []];
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_field_config_edit_form_alter')]
  public function formFieldConfigEditFormAlter(array &$form, FormStateInterface $form_state): void {
    $field_config = $form_state->getFormObject()->getEntity();
    $field_storage = $field_config->getFieldStorageDefinition();
    if ($field_storage->isLocked()) {
      return;
    }
    $field_type = $field_storage->getType();
    if (($field_type == 'file' || $field_type == 'image') && isset($form['settings']['file_directory'])) {
      // GAH! We can only support global tokens in the upload file directory
      // path.
      $form['settings']['file_directory']['#element_validate'][] = 'token_element_validate';
      // Date support needs to be implicitly added, as while technically it's
      // not a global token, it is a not only used but is the default value.
      // https://www.drupal.org/node/2642160
      $form['settings']['file_directory'] += ['#token_types' => ['date']];
      $form['settings']['file_directory']['#description'] .= ' ' . $this->t('This field supports tokens.');
    }

    // Note that the description is tokenized via token_field_widget_form_alter().
    $form['description']['#element_validate'][] = 'token_element_validate';
    $form['description'] += ['#token_types' => []];

    $form['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => [],
      '#weight' => $form['description']['#weight'] + 0.5,
    ];
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   */
  #[Hook('form_action_form_alter')]
  public function formActionFormAlter(array &$form, FormStateInterface $form_state): void {
    if (isset($form['plugin'])) {
      switch ($form['plugin']['#value']) {
        case 'action_message_action':
        case 'action_send_email_action':
        case 'action_goto_action':
          $form['token_tree'] = [
            '#theme' => 'token_tree_link',
            '#token_types' => 'all',
            '#weight' => 100,
          ];
          $form['actions']['#weight'] = 101;
          // @todo Add token validation to the action fields that can use tokens.
          break;
      }
    }
  }

  /**
   * Implements hook_form_FORM_ID_alter().
   *
   * Alters the user email fields to add token context validation and
   * adds the token tree for a better token UI and selection.
   */
  #[Hook('form_user_admin_settings_alter')]
  public function formUserAdminSettingsAlter(array &$form, FormStateInterface $form_state): void {
    $email_token_help = $this->t('Available variables are: [site:name], [site:url], [user:display-name], [user:account-name], [user:mail], [site:login-url], [site:url-brief], [user:edit-url], [user:one-time-login-url], [user:cancel-url].');

    foreach (Element::children($form) as $key) {
      $element = &$form[$key];

      // Remove the crummy default token help text.
      if (!empty($element['#description'])) {
        $element['#description'] = trim(str_replace($email_token_help, $this->t('The list of available tokens that can be used in e-mails is provided below.'), $element['#description']));
      }

      switch ($key) {
        case 'email_admin_created':
        case 'email_pending_approval':
        case 'email_no_approval_required':
        case 'email_password_reset':
        case 'email_cancel_confirm':
          // Do nothing, but allow execution to continue.
          break;

        case 'email_activated':
        case 'email_blocked':
        case 'email_canceled':
          // These fieldsets have their email elements inside a 'settings'
          // sub-element, so switch to that element instead.
          $element = &$form[$key]['settings'];
          break;

        default:
          continue 2;
      }

      foreach (Element::children($element) as $sub_key) {
        if (!isset($element[$sub_key]['#type'])) {
          continue;
        }
        elseif ($element[$sub_key]['#type'] == 'textfield' && substr($sub_key, -8) === '_subject') {
          // Add validation to subject textfields.
          $element[$sub_key]['#element_validate'][] = 'token_element_validate';
          $element[$sub_key] += ['#token_types' => ['user']];
        }
        elseif ($element[$sub_key]['#type'] == 'textarea' && substr($sub_key, -5) === '_body') {
          // Add validation to body textareas.
          $element[$sub_key]['#element_validate'][] = 'token_element_validate';
          $element[$sub_key] += ['#token_types' => ['user']];
        }
      }
    }

    // Add the token tree UI.
    $form['email']['token_tree'] = [
      '#theme' => 'token_tree_link',
      '#token_types' => ['user'],
      '#show_restricted' => TRUE,
      '#show_nested' => FALSE,
      '#weight' => 90,
    ];
  }

  /**
   * Implements hook_form_BASE_FORM_ID_alter() for node_form.
   *
   * Populates menu_link field on nodes from the menu item on unsaved nodes.
   *
   * @see menu_ui_form_node_form_submit()
   * @see token_entity_base_field_info()
   */
  #[Hook('form_node_form_alter')]
  public function formNodeFormAlter(array &$form, FormStateInterface $form_state): void {
    if (!$this->moduleHandler->moduleExists('menu_ui')) {
      return;
    }
    $form['#entity_builders'][] = 'token_node_menu_link_submit';
  }

}
