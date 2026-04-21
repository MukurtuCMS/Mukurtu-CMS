<?php

namespace Drupal\message_subscribe\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\message_subscribe\SubscribersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker to process sending of message subscriptions.
 *
 * @QueueWorker(
 *   id = "message_subscribe",
 *   title = @Translation("Process messages"),
 *   cron = {"time" = 60}
 * )
 */
class MessageSubscribe extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The message subscription service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * Constructs the queue worker.
   *
   * {@inheritdoc}
   */
  final public function __construct(array $configuration, $plugin_id, $plugin_definition, SubscribersInterface $subscribers) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->subscribers = $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('message_subscribe.subscribers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $message = $data['message'];
    $entity = $data['entity'];
    $notify_options = $data['notify_options'];
    $subscribe_options = $data['subscribe_options'];
    $context = $data['context'];

    // Reload message and entity.
    $message = $message->load($message->id());
    $entity = $entity->load($entity->id());
    if (!$entity || !$message) {
      return;
    }

    // Denotes this is being processed from a queue worker.
    $subscribe_options['queue'] = TRUE;
    $this->subscribers->sendMessage($entity, $message, $notify_options, $subscribe_options, $context);
  }

}
