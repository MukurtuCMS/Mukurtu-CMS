<?php

namespace Drupal\message_notify\Plugin\Notifier;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\message\MessageInterface;
use Drupal\message_notify\Exception\MessageNotifyException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * An abstract implementation of MessageNotifierInterface.
 */
abstract class MessageNotifierBase extends PluginBase implements MessageNotifierInterface {

  /**
   * The message entity.
   *
   * @var \Drupal\message\MessageInterface
   */
  protected $message;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The rendering service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Constructs the plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The message_notify logger channel.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendering service.
   * @param \Drupal\message\MessageInterface $message
   *   (optional) The message entity. This is required when sending or
   *   delivering a notification. If not passed to the constructor, use
   *   ::setMessage().
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerChannelInterface $logger, EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, ?MessageInterface $message = NULL) {
    // Set some defaults.
    $configuration += [
      'save on success' => TRUE,
      'save on fail' => FALSE,
    ];
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->message = $message;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MessageInterface $message = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.channel.message_notify'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $message
    );
  }

  /**
   * {@inheritdoc}
   */
  public function send() {
    $has_message = isset($this->message);
    assert($has_message, 'No message is set for this notifier.');

    $output = [];

    $view_builder = $this->entityTypeManager->getViewBuilder('message');
    foreach ($this->pluginDefinition['viewModes'] as $view_mode) {
      $build = $view_builder->view($this->message, $view_mode);
      if (version_compare(\Drupal::VERSION, '10.3.0', '<')) {
        // @phpstan-ignore-next-line
        $output[$view_mode] = $this->renderer->renderPlain($build);
      }
      else {
        $output[$view_mode] = $this->renderer->renderInIsolation($build);
      }
    }

    $result = $this->deliver($output);
    $this->postSend($result, $output);

    return $result;
  }

  /**
   * {@inheritdoc}
   *
   * - Save the rendered messages if needed.
   * - Invoke watchdog error on failure.
   */
  public function postSend($result, array $output = []) {
    $save = FALSE;
    // NULL means skip delivery. False signifies failure. Strict check.
    if ($result === FALSE) {
      $this->logger->error('Could not send message using {title} to user ID {uid}.', [
        '{title}' => $this->pluginDefinition['title'],
        '{uid}' => $this->message->getOwnerId(),
      ]);
      if ($this->configuration['save on fail']) {
        $save = TRUE;
      }
    }
    // MailManager::doMail may set $message['result'] = NULL in case sending was
    // canceled by one or more hook_mail_alter() implementations. This can
    // happen for example with Mail queue module. In that case the Message was
    // not really sent, on the other hand it didn't fail. As we won't know later
    // if it indeed was sent successfully, we take an optimistic approach, and
    // assume it will - thus saving the Message.
    elseif ($result !== FALSE && $this->configuration['save on success']) {
      $save = TRUE;
    }

    if (isset($this->configuration['rendered fields'])) {
      foreach ($this->pluginDefinition['viewModes'] as $view_mode) {
        if (empty($this->configuration['rendered fields'][$view_mode])) {
          throw new MessageNotifyException('The rendered view mode "' . $view_mode . '" cannot be saved to field, as there is not a matching one.');
        }
        $field_name = $this->configuration['rendered fields'][$view_mode];

        // @todo Inject the content_type.manager if this check is needed.
        if (!$field = $this->entityTypeManager->getStorage('field_config')->load('message.' . $this->message->bundle() . '.' . $field_name)) {
          throw new MessageNotifyException('Field "' . $field_name . '"" does not exist.');
        }

        // Get the format from the field. We assume the first delta is the
        // same as the rest.
        if (!$format = $this->message->get($field_name)->format) {
          // Field has no formatting.
          // @todo Centralize/unify rendering.
          $this->message->set($field_name, $output[$view_mode]);
        }
        else {
          $this->message->set($field_name, ['value' => $output[$view_mode], 'format' => $format]);
        }
      }
    }

    if ($save) {
      $this->message->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setMessage(MessageInterface $message) {
    $this->message = $message;
  }

}
