<?php

namespace Drupal\message_ui\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Delete multiple messages with this form.
 *
 * @package Drupal\message_ui\Form
 */
final class MessageMultipleDeleteForm extends FormBase {

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('entity_type.manager'),
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_multiple_delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    /** @var \Drupal\message\MessageTemplateInterface $templates */
    $templates = $this->entityTypeManager->getStorage('message_template')->loadMultiple();
    $options = [];

    foreach ($templates as $template) {
      $options[$template->id()] = $template->label();
    }

    $form['message_templates'] = [
      '#type' => 'select',
      '#title' => $this->t('Message types'),
      '#description' => $this->t('Select which message templates you to delete at once'),
      '#options' => $options,
      '#size' => 5,
      '#multiple' => TRUE,
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $templates = $form_state->getValue('message_templates');
    $query = $this->entityTypeManager->getStorage('message')->getQuery()
      ->accessCheck(FALSE)
      ->condition('template', $templates, 'IN');

    // Allow other modules to alter the query.
    $this->moduleHandler->alter('message_ui_multiple_message_delete_query', $query);

    // Get the messages.
    $messages = $query
      ->execute();

    $chunks = array_chunk($messages, 250);
    $operations = [];
    foreach ($chunks as $chunk) {
      $operations[] = [
        '\Drupal\message_ui\Form\MessageMultipleDeleteForm::deleteMessages',
        [$chunk],
      ];
    }

    // Set the batch.
    $batch = [
      'title' => $this->t('Deleting messages'),
      'operations' => $operations,
      'finished' => '\Drupal\message_ui\Form\MessageMultipleDeleteForm::deleteMessagesFinish',
    ];
    batch_set($batch);
  }

  /**
   * Delete multiple messages.
   *
   * @param array $mids
   *   The message IDS.
   * @param array $sandbox
   *   The sandbo object of the batch operation.
   */
  public static function deleteMessages(array $mids, array &$sandbox) {
    $messages = \Drupal::entityTypeManager()->getStorage('message')->loadMultiple($mids);
    $sandbox['message'] = t('Deleting messages between @start ot @end', [
      '@start' => reset($mids),
      '@end' => end($mids),
    ]);

    \Drupal::entityTypeManager()->getStorage('message')->delete($messages);
  }

  /**
   * Notify the people the messages were deleted.
   */
  public static function deleteMessagesFinish() {
    \Drupal::messenger()->addMessage(t('The messages were deleted.'));
  }

}
