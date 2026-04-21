<?php

namespace Drupal\message_digest\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\message_digest\DigestManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Queue worker to process sending of message subscriptions.
 *
 * @QueueWorker(
 *   id = "message_digest",
 *   title = @Translation("Process message digests"),
 *   cron = {"time" = 60}
 * )
 */
class MessageDigest extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The message digest manager.
   *
   * @var \Drupal\message_digest\DigestManagerInterface
   */
  protected $digestManager;

  /**
   * Constructs the queue worker.
   *
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, DigestManagerInterface $digest_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->digestManager = $digest_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('message_digest.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $this->digestManager->processSingleUserDigest($data['uid'], $data['notifier_id'], $data['end_time']);
  }

}
