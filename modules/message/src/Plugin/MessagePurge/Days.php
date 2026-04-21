<?php

namespace Drupal\message\Plugin\MessagePurge;

use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueInterface;
use Drupal\message\MessagePurgeBase;
use Drupal\message\MessageTemplateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Delete messages older than certain days.
 *
 * @MessagePurge(
 *   id = "days",
 *   label = @Translation("Days", context = "MessagePurge"),
 *   description = @Translation("Delete messages older than a given amount of days."),
 * )
 */
final class Days extends MessagePurgeBase {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Days constructor.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\Core\Entity\Query\QueryInterface $message_query
   *   The entity query service.
   * @param \Drupal\Core\Queue\QueueInterface $queue
   *   The message deletion queue.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack used to determine the current time.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, QueryInterface $message_query, QueueInterface $queue, RequestStack $request_stack) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $message_query, $queue);
    $this->requestStack = $request_stack;
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
      $container->get('queue')->get('message_delete'),
      $container->get('request_stack')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['days'] = [
      '#type' => 'number',
      '#min' => 1,
      '#title' => $this->t('Messages older than'),
      '#description' => $this->t('Maximal message age in days.'),
      '#default_value' => $this->configuration['days'],
      '#tree' => FALSE,
    ];

    return parent::buildConfigurationForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['days'] = $form_state->getValue('days');
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'days' => 30,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(MessageTemplateInterface $template) {
    $query = $this->baseQuery($template);
    // Find messages older than the current time minus the maximum age.
    $earlier_than = $this->requestStack->getCurrentRequest()->server->get('REQUEST_TIME') - ($this->configuration['days'] * 86400);
    $result = $query->condition('created', $earlier_than, '<')
      ->execute();
    return $result;
  }

}
