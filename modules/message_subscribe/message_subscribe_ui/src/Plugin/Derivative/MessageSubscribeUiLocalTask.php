<?php

namespace Drupal\message_subscribe_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\message_subscribe\SubscribersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides local tasks for the message subscription UI.
 */
final class MessageSubscribeUiLocalTask extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The message subscription service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * Constructs the local task deriver.
   *
   * @param \Drupal\message_subscribe\SubscribersInterface $subscribers
   *   The message subscription service.
   */
  public function __construct(SubscribersInterface $subscribers) {
    $this->subscribers = $subscribers;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new self(
      $container->get('message_subscribe.subscribers')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    $this->derivatives = [];

    $first = TRUE;
    foreach ($this->subscribers->getFlags() as $flag) {
      $this->derivatives[$flag->id()] = [
        'title' => $flag->label(),
        // First route gets the same route name as the parent (in order to
        // provide the default tab).
        'route_name' => $first ? 'message_subscribe_ui.tab' : 'message_subscribe_ui.tab.flag',
        'parent_id' => 'message_subscribe_ui.tab',
        'route_parameters' => $first ? [] : ['flag' => $flag->id()],
      ];
      $first = FALSE;
    }

    return $this->derivatives;
  }

}
