<?php

namespace Drupal\message_digest_ui\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagServiceInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Action to change digest interval.
 *
 * @Action(
 *   id = "message_digest_interval",
 *   label = @Translation("Change digest interval"),
 *   deriver = "Drupal\message_digest_ui\Plugin\Derivative\DigestIntervalActionDeriver"
 * )
 */
class DigestInterval extends ActionBase implements ContainerFactoryPluginInterface {

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The flag.
   *
   * @var \Drupal\flag\FlagInterface
   */
  protected $flag;

  /**
   * The interval plugin ID.
   *
   * @var string
   */
  protected $intervalPluginId;

  /**
   * Construct the interval action.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   The plugin ID.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FlagServiceInterface $flag_service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->flagService = $flag_service;
    if (isset($configuration['flag_id'])) {
      // Get the flag by ID.
      $this->flag = $this->flagService->getFlagById($configuration['flag_id']);
    }
    $this->intervalPluginId = $configuration['value'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('flag')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    $result = $this->flag->actionAccess('flag', $account, $object);
    return $return_as_object ? $result : $result->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $flagging = $this->flagService->getFlagging($this->flag, $entity);
    if (!$flagging) {
      $flagging = $this->flagService->flag($this->flag, $entity);
    }
    $flagging->message_digest = $this->intervalPluginId;
    $flagging->save();
  }

}
