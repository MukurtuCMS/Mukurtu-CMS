<?php

namespace Drupal\message_digest_ui\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\message_digest\Plugin\Notifier\DigestInterface;
use Drupal\message_notify\Plugin\Notifier\Manager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Derives action plugins for changing digest intervals.
 */
class DigestIntervalActionDeriver extends DeriverBase implements ContainerDeriverInterface {

  use StringTranslationTrait;

  /**
   * The message notifier plugin manager.
   *
   * @var \Drupal\message_notify\Plugin\Notifier\Manager
   */
  protected $messageNotifier;

  /**
   * The message subscribe email manager service.
   *
   * @var \Drupal\message_subscribe_email\Manager
   */
  protected $subscribeManager;

  /**
   * Constructs the action deriver.
   *
   * @param \Drupal\message_notify\Plugin\Notifier\Manager $message_notifier
   *   The notifier plugin manager.
   */
  public function __construct(Manager $message_notifier) {
    $this->messageNotifier = $message_notifier;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('plugin.message_notify.notifier.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    // This is called very early during installation, and the flag service
    // is not completely ready, so these are hardcoded for the time being.
    $flags = [
      'email_node' => 'node',
      'email_term' => 'taxonomy_term',
      'email_user' => 'user',
    ];
    foreach ($flags as $flag_id => $entity_type_id) {
      // Create a set of actions for each email flag/entity combination.
      $plugin = [
        'type' => $entity_type_id,
      ];
      foreach ($this->getDigestNotifiers() as $plugin_id => $label) {
        $id = $flag_id . ':' . ($plugin_id ?: 'immediate');
        $plugin['label'] = $label;
        $plugin['id'] = $id;
        $this->derivatives[$id] = $plugin + $base_plugin_definition;
      }
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

  /**
   * Helper method to get digest notifiers.
   *
   * Also appends the 'send immediately' non-plugin.
   */
  protected function getDigestNotifiers() {
    $values = [
      $this->t('Send immediately'),
    ];
    foreach ($this->messageNotifier->getDefinitions() as $plugin_id => $plugin_definition) {
      // Strip off the prefix.
      $plugin_id = str_replace('message_digest:', '', $plugin_id);
      if (is_subclass_of($plugin_definition['class'], DigestInterface::class)) {
        $values[$plugin_id] = $plugin_definition['title'];
      }
    }
    return $values;
  }

}
