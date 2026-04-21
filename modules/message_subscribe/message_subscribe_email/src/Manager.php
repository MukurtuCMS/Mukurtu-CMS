<?php

namespace Drupal\message_subscribe_email;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\flag\FlagServiceInterface;

/**
 * Utility functions for the Message Subscribe Email module.
 */
class Manager {

  /**
   * Message subscribe settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Construct the message subscribe email manager.
   *
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   */
  public function __construct(FlagServiceInterface $flag_service, ConfigFactoryInterface $config_factory) {
    $this->flagService = $flag_service;
    $this->config = $config_factory->get('message_subscribe_email.settings');
  }

  /**
   * Get email subscribe-related flags.
   *
   * Return Flag ids related to email subscriptions.
   *
   * The flag name should start with "email_".
   *
   * Retrieve available email flags.
   *
   * @return \Drupal\flag\FlagInterface[]
   *   An array of flags, keyed by the flag ID.
   */
  public function getFlags() {
    $email_flags = [];
    foreach ($this->flagService->getAllFlags() as $flag_name => $flag) {
      // Check that the flag is using name convention.
      if (strpos($flag_name, $this->config->get('flag_prefix')) === 0) {
        $email_flags[$flag_name] = $flag;
      }
    }

    return $email_flags;
  }

}
