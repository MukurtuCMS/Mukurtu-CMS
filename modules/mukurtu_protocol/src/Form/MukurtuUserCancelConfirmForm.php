<?php

namespace Drupal\mukurtu_protocol\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\mukurtu_core\Hook\FormHooks;
use Drupal\user\Entity\User;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionManager;
use Drupal\views_bulk_operations\Service\ViewsBulkOperationsActionProcessorInterface;
use Drupal\views_bulk_operations\Traits\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Confirmation form for the "Block or delete" bulk user action.
 *
 * Reads the Views Bulk Operations tempstore selection the same way VBO's
 * own generic confirmation form does, then presents the real account
 * cancellation options (@see user_cancel_methods()) also used by the
 * single-user /user/{uid}/cancel form.
 */
class MukurtuUserCancelConfirmForm extends FormBase {

  use ViewsBulkOperationsFormTrait;

  public function __construct(
    protected readonly PrivateTempStoreFactory $tempStoreFactory,
    protected readonly ViewsBulkOperationsActionManager $actionManager,
    protected readonly ViewsBulkOperationsActionProcessorInterface $actionProcessor,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('tempstore.private'),
      $container->get('plugin.manager.views_bulk_operations_action'),
      $container->get('views_bulk_operations.processor')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mukurtu_user_cancel_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?string $view_id = NULL, ?string $display_id = NULL) {
    $form_data = $this->getFormData($view_id, $display_id);

    if (!\array_key_exists('action_id', $form_data)) {
      return [];
    }

    $action = $this->actionManager->createInstance('mukurtu_block_user_action');
    $current_user = $this->currentUser();

    $accounts = [];
    foreach (\array_keys($form_data['list']) as $bulk_form_key) {
      $item = $this->getListItem($bulk_form_key);
      if (!$item) {
        continue;
      }
      [$uid] = $item;
      $account = User::load($uid);
      if (!$account || $account->id() <= 1 || !$action->access($account, $current_user)) {
        continue;
      }
      $accounts[$account->id()] = $account;
    }

    // Stored under the same key ViewsBulkOperationsFormTrait::cancelForm()
    // expects, so the inherited cancel button works without overriding it.
    $form_data['action_label'] = $form_data['action_label'] ?? (string) $this->t('Block or delete');
    $form_data['uids'] = \array_keys($accounts);
    $form_state->set('views_bulk_operations', $form_data);

    $form['actions'] = ['#type' => 'actions'];

    if (!$accounts) {
      $form['no_accounts'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['messages', 'messages--warning'],
          'role' => 'alert',
        ],
        'message' => [
          '#markup' => $this->t('You do not have permission to block or delete any of the selected users.'),
        ],
      ];
      $this->addCancelButton($form);
      return $form;
    }

    $names = [];
    foreach ($accounts as $account) {
      $names[$account->id()] = $account->label();
    }
    $form['accounts'] = [
      '#theme' => 'item_list',
      '#items' => $names,
    ];

    $select_cancel = $current_user->hasPermission('administer users') || $current_user->hasPermission('select account cancellation method');
    $form['user_cancel_method'] = [
      '#type' => 'radios',
      '#title' => $this->t('Cancellation method'),
      '#access' => $select_cancel,
    ];
    $form['user_cancel_method'] += user_cancel_methods();
    FormHooks::relabelCancelMethods($form);

    if (!$select_cancel) {
      $default_method = $form['user_cancel_method']['#default_value'];
      $form['user_cancel_method_show'] = [
        '#type' => 'item',
        '#title' => $this->t('When cancelling these accounts'),
        '#plain_text' => $form['user_cancel_method']['#options'][$default_method],
      ];
    }

    $form['user_cancel_confirm'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require email confirmation'),
      '#default_value' => FALSE,
      '#description' => $this->t('When enabled, the user must confirm the account cancellation via email.'),
    ];
    $form['user_cancel_notify'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Notify user when account is canceled'),
      '#default_value' => FALSE,
      '#access' => $this->config('user.settings')->get('notify.status_canceled'),
      '#description' => $this->t('When enabled, the user will receive an email notification after the account has been canceled.'),
    ];

    $form['#title'] = $this->t('Are you sure you want to block or delete the selected user(s)?');

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#button_type' => 'primary',
      '#value' => $this->t('Confirm'),
    ];
    $this->addCancelButton($form);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_data = $form_state->get('views_bulk_operations');
    $this->deleteTempstoreData($form_data['view_id'], $form_data['display_id']);

    foreach ($form_data['uids'] as $uid) {
      user_cancel($form_state->getValues(), $uid, $form_state->getValue('user_cancel_method'));
    }

    $form_state->setRedirectUrl($form_data['redirect_url']);
  }

}
