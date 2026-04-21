<?php

namespace Drupal\message_digest\Plugin\Notifier;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\State\StateInterface;
use Drupal\message\MessageInterface;
use Drupal\message_digest\Exception\InvalidDigestGroupingException;
use Drupal\message_notify\Plugin\Notifier\MessageNotifierBase;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Message Digest notifier.
 */
abstract class DigestBase extends MessageNotifierBase implements ContainerFactoryPluginInterface, DigestInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The digest interval.
   *
   * @var string
   */
  protected $digestInterval;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * The state service for tracking last sent time.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * Constructs the digest notifier plugins.
   *
   * @param array $configuration
   *   Plugin configuration array.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The message notify logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendering service.
   * @param \Drupal\message\MessageInterface $message
   *   (optional) The message entity.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, MessageInterface $message = NULL, StateInterface $state, Connection $connection, TimeInterface $time) {
    // Set some defaults.
    $configuration += [
      'entity_type' => '',
      'entity_id' => '',
    ];

    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $entity_type_manager, $renderer, $message);
    $this->connection = $connection;
    $this->digestInterval = $plugin_definition['digest_interval'];
    $this->time = $time;
    $this->state = $state;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, MessageInterface $message = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.message_notify'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $message,
      $container->get('state'),
      $container->get('database'),
      $container->get('datetime.time')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function deliver(array $output = []) {
    // Do not actually deliver this message because it will be delivered
    // via cron in a digest, but return TRUE to prevent a logged error.
    // Instead, we "deliver" it to the message_digest DB table so that it
    // can be retrieved at a later time.
    $message = $this->message;

    $message_digest = [
      'receiver' => $message->getOwnerId(),
      'entity_type' => $this->configuration['entity_type'],
      'entity_id' => $this->configuration['entity_id'],
      'notifier' => $this->getPluginId(),
      'timestamp' => $message->getCreatedTime(),
    ];

    // Don't allow entity_id without entity_type, or the reverse.
    if ($this->configuration['entity_type'] xor $this->configuration['entity_id']) {
      throw new InvalidDigestGroupingException(sprintf('Tried to create a message digest without both entity_type (%s) and entity_id (%s). These either both need to be empty, or have values.', $this->configuration['entity_type'], $this->configuration['entity_id']));
    }

    // Our $message is a cloned copy of the original $message with the mid field
    // removed to prevent overwriting (this happens in message_subscribe) so we
    // need to fetch the mid manually.
    $mid = $message->id();
    if (!$mid && isset($message->original_message)) {
      $mid = $message->original_message->id();
    }

    assert(!empty($mid), 'The message entity (or $message->original_message) must be saved in order to create a digest entry.');
    $message_digest['mid'] = $mid;

    $this->connection->insert('message_digest')->fields($message_digest)->execute();

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getInterval() {
    return $this->digestInterval;
  }

  /**
   * {@inheritdoc}
   */
  public function getRecipients() {
    $query = $this->connection->select('message_digest', 'md');
    $query->fields('md', ['receiver']);
    $query->condition('timestamp', $this->getEndTime(), '<=');
    $query->condition('sent', 0);
    $query->condition('notifier', $this->getPluginId());
    $query->distinct();

    return $query->execute()->fetchCol();
  }

  /**
   * {@inheritdoc}
   */
  public function aggregate($uid, $end) {
    $message_groups = [];

    $query = $this->connection->select('message_digest', 'md');
    $query->fields('md')
      ->condition('timestamp', $end, '<=')
      ->condition('receiver', $uid)
      ->condition('sent', 0)
      ->condition('notifier', $this->getPluginId());
    $query->orderBy('id');
    $result = $query->execute();

    foreach ($result as $row) {
      $entity_type = $row->entity_type;
      $entity_id = $row->entity_id;

      $context = [
        'data' => $row,
        // Set this to zero to aggregate group content.
        'entity_type' => $entity_type,
        'entity_id' => $entity_id,
      ];
      if (!empty($context['data']->mid)) {
        $message_groups[$context['entity_type']][$context['entity_id']][] = $context['data']->mid;
      }
    }
    return $message_groups;
  }

  /**
   * Determine if it is time to process this digest or not.
   *
   * @return bool
   *   Returns TRUE if a sufficient amount of time has passed.
   */
  public function processDigest() {
    $interval = $this->getInterval();
    $key = $this->pluginId . '_last_run';
    $last_run = $this->state->get($key, 0);
    // Allow some buffer for environmental differences that cause minor
    // variations in the request times. This will prevent digest processing from
    // being pushed to the next cron run when it really should be going this
    // time.
    $buffer = 30;
    return $last_run < strtotime('-' . $interval, $this->time->getRequestTime()) + $buffer;
  }

  /**
   * {@inheritdoc}
   */
  public function markSent(UserInterface $account, $last_mid) {
    $this->connection->update('message_digest')
      ->fields(['sent' => 1])
      ->condition('receiver', $account->id())
      ->condition('notifier', $this->getPluginId())
      ->condition('mid', $last_mid, '<=')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getEndTime() {
    return $this->time->getRequestTime();
  }

  /**
   * {@inheritdoc}
   */
  public function setLastSent() {
    $this->state->set($this->getPluginId() . '_last_run', $this->time->getRequestTime());
  }

  /**
   * Implements the magic __sleep() method.
   */
  public function __sleep() {
    // Only serialize the local properties, ignoring all dependencies from the
    // container. The database connection cannot be serialized and neither can
    // other services like the state service and the entity type manager since
    // they in turn also depend on an active database connection.
    return [
      'configuration',
      'pluginId',
      'pluginDefinition',
      'message',
      'digestInterval',
    ];
  }

  /**
   * Implements the magic __wakeup() method.
   */
  public function __wakeup() {
    // Restore the database connection.
    $this->connection = Database::getConnection();

    // Restore the dependencies from the container.
    $container = \Drupal::getContainer();
    $this->entityTypeManager = $container->get('entity_type.manager');
    $this->logger = $container->get('logger.channel.message_notify');
    $this->renderer = $container->get('renderer');
    $this->state = $container->get('state');
    $this->time = $container->get('datetime.time');
  }

}
