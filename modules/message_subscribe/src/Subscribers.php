<?php

namespace Drupal\message_subscribe;

use Drupal\comment\CommentInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\MessageNotifier;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Drupal\user\EntityOwnerInterface;
use Psr\Log\LoggerInterface;

/**
 * A message subscribers service.
 */
class Subscribers implements SubscribersInterface {

  /**
   * The message subscribe settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The flag manager service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message notification service.
   *
   * @var \Drupal\message_notify\MessageNotifier
   */
  protected $messageNotifier;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The message subscribe queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Debugging enabled.
   *
   * @var bool
   */
  protected $debug = FALSE;

  /**
   * Construct the service.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag manager service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\message_notify\MessageNotifier $message_notifier
   *   The message notification service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Queue\QueueFactory $queue
   *   The queue service.
   */
  public function __construct(FlagServiceInterface $flag_service, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, MessageNotifier $message_notifier, ModuleHandlerInterface $module_handler, QueueFactory $queue) {
    $this->config = $config_factory->get('message_subscribe.settings');
    $this->entityTypeManager = $entity_type_manager;
    $this->flagService = $flag_service;
    $this->messageNotifier = $message_notifier;
    $this->moduleHandler = $module_handler;
    $this->queue = $queue->get('message_subscribe');
    $this->debug = $this->config->get('debug_mode');
  }

  /**
   * Sets the logger channel.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The message_subscribe logger channel.
   *
   * @todo Inject this service in the 2.x version
   */
  public function setLoggerChannel(LoggerInterface $logger) {
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public function sendMessage(EntityInterface $entity, MessageInterface $message, array $notify_options = [], array $subscribe_options = [], array $context = []) {
    $use_queue = $subscribe_options['use queue'] ?? $this->config->get('use_queue');
    $notify_message_owner = $subscribe_options['notify message owner'] ?? $this->config->get('notify_own_actions');
    $range = $this->config->get('range') ?? 100;
    // Save message by default.
    $subscribe_options += [
      'save message' => TRUE,
      'skip context' => FALSE,
      'last uid' => 0,
      'uids' => [],
      'range' => $use_queue ? $range : FALSE,
      'end time' => FALSE,
      'use queue' => $use_queue,
      'queue' => FALSE,
      'entity access' => TRUE,
      'notify blocked users' => FALSE,
      'notify message owner' => $notify_message_owner,
    ];

    if (empty($message->id()) && $subscribe_options['save message']) {
      $message->save();
    }

    if ($use_queue && empty($subscribe_options['queue'])) {
      if (empty($message->id())) {
        throw new MessageSubscribeException('Cannot add a non-saved message to the queue.');
      }

      // Get the context once, so we don't need to process it every time
      // a worker claims the item.
      $context = $context ?: $this->getBasicContext($entity, $subscribe_options['skip context'], $context);

      // Context is already set, skip when processing queue item.
      $subscribe_options['skip context'] = TRUE;

      // Add item to the queue.
      $task = [
        'message' => $message,
        // Clone the entity first to avoid any oddness with serialization.
        // @see https://www.drupal.org/project/drupal/issues/2971157
        'entity' => clone $entity,
        'notify_options' => $notify_options,
        'subscribe_options' => $subscribe_options,
        'context' => $context,
      ];

      // Exit now, as messages will be processed via queue API.
      $this->queue->createItem($task);
      return;
    }

    $message->message_subscribe = [];

    // Retrieve all users subscribed.
    $uids = [];
    if ($subscribe_options['uids']) {
      // We got a list of user IDs directly from the implementing module,
      // However we need to adhere to the range.
      $offset = 0;
      if ($subscribe_options['last uid']) {
        $offset = array_search($subscribe_options['last uid'], array_keys($subscribe_options['uids'])) + 1;
      }

      $uids = $subscribe_options['range'] ? array_slice($subscribe_options['uids'], $offset, $subscribe_options['range'], TRUE) : $subscribe_options['uids'];
    }
    if (empty($uids) && !$uids = $this->getSubscribers($entity, $message, $subscribe_options, $context)) {
      // If we use a queue, it will be deleted.
      return;
    }
    $this->debug('Preparing to process subscriptions for users: @uids', ['@uids' => implode(', ', array_keys($uids))]);
    $last_uid = NULL;
    foreach ($uids as $uid => $delivery_candidate) {
      $last_uid = $uid;
      // Clone the message in case it will need to be saved, it won't
      // overwrite the existing one.
      $cloned_message = $message->createDuplicate();
      // Push a copy of the original message into the new one. The key
      // `original` is not used here as that has special meaning and can prevent
      // field values from being saved.
      // @see SqlContentEntityStorage::saveToDedicatedTables().
      $cloned_message->original_message = $message;
      // Set the owner to this user.
      $cloned_message->setOwnerId($delivery_candidate->getAccountId());

      // Allow modules to alter the message for the specific user.
      $this->moduleHandler->alter('message_subscribe_message', $cloned_message, $delivery_candidate);

      // Send the message using the required notifiers.
      $this->debug(
        'Preparing delivery for uid @user with notifiers @notifiers',
        [
          '@user' => $uid,
          '@notifiers' => implode(', ', $delivery_candidate->getNotifiers()),
        ]
      );
      foreach ($delivery_candidate->getNotifiers() as $notifier_name) {
        $options = !empty($notify_options[$notifier_name]) ? $notify_options[$notifier_name] : [];
        $options += [
          'save on fail' => FALSE,
          'save on success' => FALSE,
          'context' => $context,
        ];

        $result = $this->messageNotifier->send($cloned_message, $options, $notifier_name);
        $this->debug($result ? 'Successfully sent message via notifier @notifier to user @uid' : 'Failed to send message via notifier @notifier to user @uid', [
          '@notifier' => $notifier_name,
          '@uid' => $uid,
        ]);

        // Check we didn't timeout.
        if ($use_queue && $subscribe_options['end time'] && time() < $subscribe_options['end time']) {
          continue 2;
        }
      }
    }

    $last_key = key(array_slice($subscribe_options['uids'], -1, 1, TRUE));

    // Last key could not be found which means there are no more queue items to
    // create.
    if ($last_key === NULL) {
      return;
    }

    if ($use_queue && isset($last_uid) && $last_key != $last_uid) {
      // Add item to the queue.
      $task = [
        'message' => $message,
        'entity' => $entity,
        'notify_options' => $notify_options,
        'subscribe_options' => $subscribe_options,
        'context' => $context,
      ];

      $task['subscribe_options']['last uid'] = $last_uid;
      $this->debug('New batch queue with last uid of @uid', ['@uid' => $last_uid]);

      // Create a new queue item, with the last user ID.
      $this->queue->createItem($task);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSubscribers(EntityInterface $entity, MessageInterface $message, array $options = [], array &$context = []) {
    $context = !empty($context) ? $context : $this->getBasicContext($entity, !empty($options['skip context']), $context);
    $notify_message_owner = $options['notify message owner'] ?? $this->config->get('notify_own_actions');

    $uids = [];

    $this->moduleHandler->invokeAllWith('message_subscribe_get_subscribers', function (callable $hook, string $module) use (&$uids, $message, $options, $context) {
      $result = $hook($message, $options, $context);
      $this->debug(
        'Found @uids from @function',
        [
          '@uids' => implode(', ', array_keys($result)),
          '@function' => $module . '_message_subscribe_get_subscribers',
        ]
      );
      $uids += $result;
    });

    // If we're not notifying blocked users, exclude those users from the result
    // set now so that we avoid unnecessarily loading those users later.
    if (empty($options['notify blocked users']) && !empty($uids)) {
      $query = $this->entityTypeManager->getStorage('user')->getQuery();
      $results = $query
        ->accessCheck(TRUE)
        ->condition('status', 1)
        ->condition('uid', array_keys($uids), 'IN')
        ->execute();

      if (!empty($results)) {
        $uids = array_intersect_key($uids, $results);
      }
      else {
        // There are no blocked users to notify.
        $uids = [];
      }
    }

    foreach ($uids as $uid => $values) {
      // See if the author of the entity gets notified.
      if (!$notify_message_owner && $this->isEntityOwner($entity, $uid)) {
        $this->debug('Removing @uid from recipient list since they are the entity owner.', ['@uid' => $uid]);
        unset($uids[$uid]);
      }

      if (!empty($options['entity access'])) {
        $account = $this->entityTypeManager->getStorage('user')->load($uid);
        if (!$entity->access('view', $account)) {
          // User doesn't have access to view the entity.
          $this->debug('Removing @uid from recipient list since they do not have view access.', ['@uid' => $uid]);
          unset($uids[$uid]);
        }
      }
    }

    $this->debug('Recipients after access filter and entity owner filter: @uids', ['@uids' => implode(', ', array_keys($uids))]);

    $values = [
      'context' => $context,
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity,
      'message' => $message,
      'subscribe_options' => $options,
    ];

    $this->addDefaultNotifiers($uids);
    $this->debug('Recipient list after default notifiers: @uids', ['@uids' => implode(', ', array_keys($uids))]);

    $this->moduleHandler->alter('message_subscribe_get_subscribers', $uids, $values);
    ksort($uids);
    $this->debug('Recipient list after ksort and alter hook: @uids', ['@uids' => implode(', ', array_keys($uids))]);

    return $uids;

  }

  /**
   * Helper method to determine if the given entity belongs to the given user.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check ownership of.
   * @param int $uid
   *   The user ID to check for ownership.
   *
   * @return bool
   *   Returns TRUE if the entity is owned by the given user ID.
   */
  protected function isEntityOwner(EntityInterface $entity, $uid) {
    // Special handling for entities implementing RevisionLogInterface.
    $is_owner = FALSE;
    if ($entity instanceof RevisionLogInterface) {
      $is_owner = $entity->getRevisionUserId() == $uid;
    }
    elseif ($entity instanceof EntityOwnerInterface) {
      $is_owner = $entity->getOwnerId() == $uid;
    }

    return $is_owner;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlags($entity_type = NULL, $bundle = NULL, ?AccountInterface $account = NULL) {
    $flags = $this->flagService->getAllFlags($entity_type, $bundle);
    if ($account) {
      // Filter flags down to ones the account has action access for.
      // @see https://www.drupal.org/node/2870375
      foreach ($flags as $flag_id => $flag) {
        if (!$flag->actionAccess('flag', $account)->isAllowed()
        && !$flag->actionAccess('unflag', $account)->isAllowed()) {
          unset($flags[$flag_id]);
        }
      }
    }
    $ms_flags = [];
    $prefix = $this->config->get('flag_prefix') . '_';
    foreach ($flags as $flag_name => $flag) {
      // Check that the flag is using name convention.
      if (strpos($flag_name, $prefix) === 0) {
        $ms_flags[$flag_name] = $flag;
      }
    }

    return $ms_flags;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasicContext(EntityInterface $entity, $skip_detailed_context = FALSE, array $context = []) {
    if (empty($context)) {
      $id = $entity->id();
      $context[$entity->getEntityTypeId()][$id] = $id;
    }

    if ($skip_detailed_context) {
      return $context;
    }

    $context += [
      'node' => [],
      'user' => [],
      'taxonomy_term' => [],
    ];

    // Default context for comments.
    if ($entity instanceof CommentInterface) {
      $context['node'][$entity->getCommentedEntityId()] = $entity->getCommentedEntityId();
      $context['user'][$entity->getOwnerId()] = $entity->getOwnerId();
    }

    if (empty($context['node'])) {
      return $context;
    }

    /** @var \Drupal\node\NodeInterface[] $nodes */
    $nodes = $this->entityTypeManager->getStorage('node')->loadMultiple($context['node']);

    foreach ($nodes as $node) {
      $context['user'][$node->getOwnerId()] = $node->getOwnerId();

      if ($this->moduleHandler->moduleExists('taxonomy')) {
        // Iterate over all taxonomy term reference fields, or entity-reference
        // fields that reference terms.
        foreach ($node->getFieldDefinitions() as $field) {
          if ($field->getType() != 'entity_reference' || $field->getSetting('target_type') != 'taxonomy_term') {
            // Not an entity reference field or not referencing a taxonomy term.
            continue;
          }
          // Add referenced terms.
          foreach ($node->get($field->getName()) as $tid) {
            $context['taxonomy_term'][$tid->target_id] = $tid->target_id;
          }
        }
      }
    }

    return $context;
  }

  /**
   * Get the default notifiers for a given set of users.
   *
   * @param \Drupal\message_subscribe\Subscribers\DeliveryCandidateInterface[] &$uids
   *   An array detailing notification info for users.
   */
  protected function addDefaultNotifiers(array &$uids) {
    $notifiers = $this->config->get('default_notifiers');
    if (empty($notifiers)) {
      return;
    }
    // Use notifier names as keys to avoid potential duplication of notifiers
    // by other modules' hooks.
    foreach (array_keys($uids) as $uid) {
      foreach ($notifiers as $notifier) {
        $uids[$uid]->addNotifier($notifier);
      }
    }
  }

  /**
   * Wrapper to the logger channel to only log if debugging is enabled.
   *
   * @param string $message
   *   The message to log.
   * @param array $context
   *   The replacement patterns.
   */
  protected function debug($message, array $context = []) {
    if (!$this->debug) {
      return;
    }
    $this->logger->debug($message, $context);
  }

}
