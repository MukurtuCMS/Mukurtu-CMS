<?php

namespace Drupal\message_ui\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\Core\Url;
use Drupal\message\Entity\Message;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides a message deletion confirmation form.
 */
final class DeleteMultiple extends ConfirmFormBase {

  /**
   * The array of messages to delete.
   *
   * @var array
   */
  protected $messages = [];

  /**
   * The tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * The message storage.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $manager;

  /**
   * The message storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $storage;

  /**
   * The redirect destination.
   *
   * @var string
   */
  protected $redirectDestination;

  /**
   * The string translation service.
   *
   * @var \Drupal\Core\StringTranslation\TranslationInterface
   */
  protected $stringTranslation;

  /**
   * The current user ID.
   *
   * @var int
   */
  protected $currentUserId;

  /**
   * Constructs a DeleteMultiple form object.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The tempstore factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $manager
   *   The entity manager.
   * @param string $redirect_destination
   *   The redirect destination.
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The translation service.
   * @param int $current_user_id
   *   The current user ID.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    PrivateTempStoreFactory $temp_store_factory,
    EntityTypeManagerInterface $manager,
    string $redirect_destination,
    TranslationInterface $string_translation,
    int $current_user_id) {

    $this->tempStoreFactory = $temp_store_factory;
    $this->storage = $manager->getStorage('message');
    $this->redirectDestination = $redirect_destination;
    $this->stringTranslation = $string_translation;
    $this->currentUserId = $current_user_id;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('tempstore.private'),
      $container->get('entity_type.manager'),
      $container->get('request_stack')->getCurrentRequest()->query->get('q'),
      $container->get('string_translation'),
      $container->get('current_user')->id()
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'message_multiple_delete_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->stringTranslation
      ->formatPlural(count($this->messages), 'Are you sure you want to delete this item?', 'Are you sure you want to delete these items?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelRoute() {}

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // @todo below is from Message module, remove?
    $this->messages = $this->tempStoreFactory
      ->get('message_multiple_delete_confirm')->get($this->currentUserId);

    if (empty($this->messages)) {
      return new RedirectResponse($this->getCancelUrl()->setAbsolute()->toString());
    }

    $form['messages'] = [
      '#theme' => 'item_list',
      '#items' => array_map([$this, 'filterCallback'], $this->messages),
    ];
    $form = parent::buildForm($form, $form_state);

    $form['actions']['cancel']['#href'] = $this->getCancelRoute();

    // @todo See "Delete multiple messages" from message_ui in D7.
    return $form;
  }

  /**
   * Filter callback; Set text for each message which will be deleted.
   *
   * @param \Drupal\message\Entity\Message $message
   *   The message object.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   A simple text to show which message is deleted.
   */
  private function filterCallback(Message $message) {
    $params = [
      '@id' => $message->id(),
      '@template' => $message->getTemplate()->label(),
    ];

    return $this->t('Delete message ID @id fo template @template', $params);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get the message IDs.
    $query = $this->storage->getQuery();
    $result = $query
      ->condition('type', $form_state['values']['types'], 'IN')
      ->accessCheck(FALSE)
      ->execute();

    if (empty($result['message'])) {
      // No messages found, return.
      $this->messenger()->addError($this->t('No messages were found according to the parameters you entered'));
      return;
    }

    // Prepare the message IDs chunk array for batch operation.
    $chunks = array_chunk(array_keys($result['message']), 100);
    $operations = [];

    // @todo update the operation below to new structure.
    foreach ($chunks as $chunk) {
      $operations[] = ['message_delete_multiple', [$chunk]];
    }

    // Set the batch.
    $batch = [
      'operations' => $operations,
      'title' => $this->t('deleting messages.'),
      'init_message' => $this->t('Starting to delete messages.'),
      'progress_message' => $this->t('Processed @current out of @total.'),
      'error_message' => $this->t('The batch operation has failed.'),
    ];
    batch_set($batch);
    batch_process($this->redirectDestination);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('message.messages');
  }

}
