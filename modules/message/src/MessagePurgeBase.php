<?php

namespace Drupal\message;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Base implementation for MessagePurge plugins.
 */
abstract class MessagePurgeBase extends PluginBase implements MessagePurgeInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The weight of the purge plugin.
   *
   * @var int|string
   */
  protected $weight = 0;

  /**
   * The entity query object for Message items.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $messageQuery;

  /**
   * The message deletion queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * {@inheritdoc}
   */
  public function process(array $ids) {
    if (!empty($ids)) {
      foreach (array_chunk($ids, MessagePurgeInterface::MESSAGE_DELETE_SIZE) as $queue_set) {
        $this->queue->createItem($queue_set);
      }
    }
  }

  /**
   * Get a base query.
   *
   * @param \Drupal\message\MessageTemplateInterface $template
   *   The message template for which to fetch messages.
   *
   * @return \Drupal\Core\Entity\Query\QueryInterface
   *   The query object.
   */
  protected function baseQuery(MessageTemplateInterface $template) {
    return $this->messageQuery
      // Access is not checked since this is simply called to remove messages,
      // which typically happens during cron or queue processing.
      ->accessCheck(FALSE)
      ->condition('template', $template->id())
      ->sort('created', 'DESC')
      ->sort('mid', 'DESC');
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function description() {
    return $this->pluginDefinition['description'];
  }

  /**
   * {@inheritdoc}
   */
  public function setWeight($weight) {
    $this->weight = $weight;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWeight() {
    return $this->weight;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return [
      'id' => $this->getPluginId(),
      'weight' => $this->getWeight(),
      'data' => $this->configuration,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $configuration += [
      'data' => [],
      'weight' => 0,
    ];
    $this->configuration = $configuration['data'] + $this->defaultConfiguration();
    $this->weight = $configuration['weight'];
    return $this;
  }

}
