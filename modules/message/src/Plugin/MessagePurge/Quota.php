<?php

namespace Drupal\message\Plugin\MessagePurge;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\message\MessagePurgeBase;
use Drupal\message\MessageTemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Maximal (approximate) amount of messages.
 *
 * @MessagePurge(
 *   id = "quota",
 *   label = @Translation("Quota", context = "MessagePurge"),
 *   description = @Translation("Maximal (approximate) amount of messages to keep."),
 * )
 */
class Quota extends MessagePurgeBase {

  /**
   * Constructs a MessagePurgeBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\Query\QueryInterface $message_query
   *   The entity query object for message items.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The message deletion queue.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueryInterface $message_query, QueueInterface $queue) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->messageQuery = $message_query;
    $this->queue = $queue;

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')->getStorage('message')->getQuery(),
      $container->get('queue')->get('message_delete')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['quota'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Messages quota'),
      '#description' => $this->t('Maximal (approximate) amount of messages.'),
      '#default_value' => $this->configuration['quota'],
      '#tree' => FALSE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['quota'] = $form_state->getValue('quota');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'quota' => 1000,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(MessageTemplateInterface $template) {
    $query = $this->baseQuery($template);
    $result = $query
      // We need some kind of limit in order to get any results, but we really
      // want all of them, so use an arbitrarily large number.
      ->range($this->configuration['quota'], 1000000)
      ->execute();
    return $result;
  }

}
