<?php

namespace Drupal\mukurtu_core\Form;

use Drupal\Core\Action\ActionManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MukurtuUserBulkForm extends FormBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected ActionManager $actionManager,
  ) {}

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.action'),
    );
  }

  public function getFormId(): string {
    return 'mukurtu_user_bulk_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    /** @var \Drupal\mukurtu_core\MukurtuUserListBuilder $listBuilder */
    $listBuilder = $this->entityTypeManager->getListBuilder('user');
    $entities = $listBuilder->load();

    $options = [];
    foreach ($entities as $entity) {
      $options[$entity->id()] = $listBuilder->buildRow($entity);
    }

    $form['users'] = [
      '#type' => 'tableselect',
      '#header' => $listBuilder->buildHeader(),
      '#options' => $options,
      '#empty' => $this->t('No users found.'),
    ];

    $form['pager'] = ['#type' => 'pager'];

    $form['action'] = [
      '#type' => 'select',
      '#title' => $this->t('With selected'),
      '#options' => ['' => $this->t('- Select action -')] + $this->getActionOptions(),
    ];

    $form['apply'] = [
      '#type' => 'submit',
      '#value' => $this->t('Apply to selected items'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  protected function getActionOptions(): array {
    $options = [];
    foreach ($this->actionManager->getDefinitions() as $id => $definition) {
      if (isset($definition['type']) && $definition['type'] === 'user') {
        $options[$id] = (string) $definition['label'];
      }
    }
    asort($options);
    return $options;
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (empty(array_filter($form_state->getValue('users', [])))) {
      $form_state->setErrorByName('users', $this->t('Select at least one user.'));
    }
    if (empty($form_state->getValue('action'))) {
      $form_state->setErrorByName('action', $this->t('Select an action to apply.'));
    }
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $selected_uids = array_keys(array_filter($form_state->getValue('users', [])));
    $action_id = $form_state->getValue('action');

    /** @var \Drupal\Core\Action\ActionInterface $action */
    $action = $this->actionManager->createInstance($action_id);
    $entities = $this->entityTypeManager->getStorage('user')->loadMultiple($selected_uids);
    $action->executeMultiple($entities);

    $this->messenger()->addStatus(
      $this->formatPlural(
        count($selected_uids),
        'Applied %action to 1 user.',
        'Applied %action to @count users.',
        ['%action' => $action->label()]
      )
    );
  }

}
