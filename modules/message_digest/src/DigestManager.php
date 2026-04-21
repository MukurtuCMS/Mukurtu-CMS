<?php

namespace Drupal\message_digest;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\message_digest\Plugin\Notifier\DigestInterface;
use Drupal\message_notify\Plugin\Notifier\Manager;
use Drupal\user\UserInterface;

/**
 * Digest manager service.
 */
class DigestManager implements DigestManagerInterface {

  /**
   * Database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The digest formatter service.
   *
   * @var \Drupal\message_digest\DigestFormatterInterface
   */
  protected $formatter;

  /**
   * The mail manager service.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   *
   * @todo This can be removed if/when the message notify sender service is
   *   used. @see https://www.drupal.org/node/2103013
   */
  protected $mailManager;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Message notifier plugin manager.
   *
   * @var \Drupal\message_notify\Plugin\Notifier\Manager
   */
  protected $notifierManager;

  /**
   * The message digest queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs the message digest manager service.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   The active database connection.
   * @param \Drupal\message_notify\Plugin\Notifier\Manager $notifier_manager
   *   The message notifier plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\message_digest\DigestFormatterInterface $formatter
   *   The digest formatter service.
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager service.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue factory service.
   */
  public function __construct(Connection $connection, Manager $notifier_manager, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, DigestFormatterInterface $formatter, MailManagerInterface $mail_manager, QueueFactory $queue) {
    $this->database = $connection;
    $this->entityTypeManager = $entity_type_manager;
    $this->formatter = $formatter;
    $this->mailManager = $mail_manager;
    $this->moduleHandler = $module_handler;
    $this->notifierManager = $notifier_manager;
    $this->queue = $queue->get('message_digest');
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupOldMessages() {
    // Removes any message entries from the message_digest table if the
    // corresponding message entity has been deleted.
    // The delete method cannot use a JOIN, so first query to find entries to
    // delete.
    // @see https://www.drupal.org/node/2693899
    $query = $this->database->select('message_digest', 'md');
    $query->leftJoin('message', 'm', 'md.mid = m.mid');
    $query->isNull('m.mid');
    $query->fields('md', ['mid']);
    $query->condition('md.sent', 1);
    $mids = $query->execute()->fetchCol();

    if (!empty($mids)) {
      $this->database->delete('message_digest')
        ->condition('mid', $mids, 'IN')
        ->condition('sent', 1)
        ->execute();
    }
  }

  /**
   * Send the actual digest email.
   *
   * @param \Drupal\user\UserInterface $account
   *   Account to deliver the message to.
   * @param string $entity_type
   *   The entity type. Leave empty for global digests.
   * @param string|int $entity_id
   *   The entity ID. Leave empty for global digests.
   * @param \Drupal\message_digest\Plugin\Notifier\DigestInterface $notifier
   *   The digest notifier plugin ID used for this digest.
   * @param string $formatted_message
   *   The formatted message.
   */
  protected function deliverDigest(UserInterface $account, $entity_type, $entity_id, DigestInterface $notifier, $formatted_message) {
    $params = [
      'body' => $formatted_message,
      'entity_type' => $entity_type,
      'entity_id' => $entity_id,
      'notifier' => $notifier,
    ];
    // @todo Use the message_notify sender service.
    // @see https://www.drupal.org/node/2103013
    $this->mailManager->mail('message_digest', 'digest', $account->getEmail(), $account->getPreferredLangcode(), $params);
  }

  /**
   * {@inheritdoc}
   */
  public function processDigests() {
    foreach ($this->getNotifiers() as $notifier) {
      if ($notifier->processDigest()) {
        // Gather up all the messages into neat little digests and send 'em out.
        // It is up to each digest plugin to manage last sent time, etc.
        // @see \Drupal\message_digest\Plugin\Notifier\DigestBase
        $recipients = $notifier->getRecipients();
        $end_time = $notifier->getEndTime();
        foreach ($recipients as $uid) {
          // Queue each recipient digest for processing and sending.
          $data = [
            'uid' => $uid,
            'notifier_id' => $notifier->getPluginId(),
            'end_time' => $end_time,
          ];
          $this->queue->createItem($data);
        }
        $notifier->setLastSent();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNotifiers() {
    $notifiers = [];

    foreach ($this->notifierManager->getDefinitions() as $plugin_id => $plugin_definition) {
      $notifier = $this->notifierManager->createInstance($plugin_id, []);
      if (!$notifier instanceof DigestInterface) {
        // Only load the "Digest" notifiers and skip the rest.
        continue;
      }
      $notifiers[$notifier->getPluginId()] = $notifier;
    }

    return $notifiers;
  }

  /**
   * {@inheritdoc}
   */
  public function processSingleUserDigest($account_id, $notifier_id, $end_time) {
    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entityTypeManager->getStorage('user')->load($account_id);

    // If the user has been deleted, do not attempt to send any messages.
    if (empty($account)) {
      return;
    }

    /** @var \Drupal\message_digest\Plugin\Notifier\DigestInterface $notifier */
    $notifier = $this->notifierManager->createInstance($notifier_id);
    assert($notifier instanceof DigestInterface, 'Notifier ID ' . $notifier_id . ' is not an instance of DigestInterface.');

    $plugin_definition = $notifier->getPluginDefinition();
    $view_modes = array_combine($plugin_definition['viewModes'], $plugin_definition['viewModes']);
    $digests = $notifier->aggregate($account_id, $end_time);
    $max_mid = 0;
    foreach ($digests as $entity_type => $entity_ids) {
      foreach ($entity_ids as $entity_id => $message_ids) {
        $last_mid = max($message_ids);
        $max_mid = ($last_mid > $max_mid) ? $last_mid : $max_mid;
        // Load up the messages.
        $messages = $this->entityTypeManager->getStorage('message')->loadMultiple($message_ids);
        if (empty($messages)) {
          continue;
        }
        $context = [
          'deliver' => TRUE,
          'entity_type' => $entity_type,
          'entity_id' => $entity_id,
          'messages' => $messages,
          'notifier_id' => $notifier_id,
          'view_modes' => $view_modes,
        ];

        $this->moduleHandler->alter('message_digest_aggregate', $context, $account, $notifier);
        $this->moduleHandler->alter('message_digest_view_mode', $context, $notifier, $account);
        if ($context['deliver']) {
          $formatted_messages = $this->formatter->format($context['messages'], $context['view_modes'], $account);
          $this->deliverDigest($account, $context['entity_type'], $context['entity_id'], $notifier, $formatted_messages);
        }
      }
    }
    $notifier->markSent($account, $max_mid);
  }

}
