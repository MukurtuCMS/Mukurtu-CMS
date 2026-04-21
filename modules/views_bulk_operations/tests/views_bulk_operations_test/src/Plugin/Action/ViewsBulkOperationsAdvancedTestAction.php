<?php

declare(strict_types=1);

namespace Drupal\views_bulk_operations_test\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\Entity\Node;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsPreconfigurationInterface;

/**
 * Action for test purposes only.
 */
#[Action(
  id: 'views_bulk_operations_advanced_test_action',
  label: new TranslatableMarkup('VBO example action'),
  type: 'node'
)]
final class ViewsBulkOperationsAdvancedTestAction extends ViewsBulkOperationsActionBase implements ViewsBulkOperationsPreconfigurationInterface, PluginFormInterface {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function execute(?Node $entity = NULL): TranslatableMarkup {
    // Check if context array has been passed to the action.
    if (\count($this->context) === 0) {
      throw new \Exception('Context array empty in action object.');
    }

    $this->messenger()->addMessage(\sprintf('Test action (preconfig: %s, config: %s, label: %s)',
      $this->configuration['test_preconfig'],
      $this->configuration['test_config'],
      $entity->label()
    ));

    // Unpublish entity.
    if ($this->configuration['test_config'] === 'unpublish') {
      if (!$entity->isDefaultTranslation()) {
        $entity = Node::load($entity->id());
      }
      $entity->setUnpublished();
      $entity->save();
    }

    return $this->t('Test');
  }

  /**
   * {@inheritdoc}
   */
  public function buildPreConfigurationForm(array $element, array $values, FormStateInterface $form_state): array {
    $element['test_preconfig'] = [
      '#title' => $this->t('Preliminary configuration'),
      '#type' => 'textfield',
      '#default_value' => $values['preconfig'] ?? '',
    ];
    return $element;
  }

  /**
   * Configuration form builder.
   *
   * @param array $form
   *   Form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   *
   * @return array
   *   The configuration form.
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['test_config'] = [
      '#title' => $this->t('Config'),
      '#type' => 'textfield',
      '#default_value' => $form_state->getValue('config'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
