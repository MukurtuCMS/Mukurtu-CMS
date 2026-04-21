<?php

namespace Drupal\message\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes a set of messages.
 *
 * No more than MessagePurgeInterface::MESSAGE_DELETE_SIZE messages should be
 * given to a single queue item to ensure that the worker can complete the task
 * within PHP operating constraints.
 *
 * @QueueWorker(
 *   id = "message_delete",
 *   title = @Translation("Delete messages"),
 *   cron = {"time" = 10}
 * )
 */
final class MessageDeletionWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The message storage handler.
   *
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $messageStorage;

  /**
   * Constructs a new MessageDeletionWorker object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messageStorage = $entity_type_manager->getStorage('message');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    if (!empty($data)) {
      $messages = $this->messageStorage->loadMultiple($data);
      $this->messageStorage->delete($messages);
    }
  }

}
