<?php

namespace Drupal\message_subscribe_email\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\flag\Event\FlagEvents as Flag;
use Drupal\flag\Event\FlaggingEvent;
use Drupal\flag\Event\UnflaggingEvent;
use Drupal\flag\FlaggingInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * React to flag and unflag events.
 */
class FlagEvents implements EventSubscriberInterface {

  /**
   * Config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * Construct the flag event subscriber.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, FlagServiceInterface $flag_service) {
    $this->configFactory = $config_factory;
    $this->flagService = $flag_service;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events[Flag::ENTITY_FLAGGED] = ['onFlag', 50];
    $events[Flag::ENTITY_UNFLAGGED] = ['onUnflag', 50];
    return $events;
  }

  /**
   * React to entity flagging.
   *
   * @param \Drupal\flag\Event\FlaggingEvent $event
   *   The flagging event.
   */
  public function onFlag(FlaggingEvent $event) {
    $this->triggerEmailFlag($event->getFlagging(), 'flag');
  }

  /**
   * React to entity unflagging.
   *
   * @param \Drupal\flag\Event\UnflaggingEvent $event
   *   The flagging event.
   */
  public function onUnflag(UnflaggingEvent $event) {
    // Unflagging can happen in bulk, so loop through all flaggings.
    foreach ($event->getFlaggings() as $flagging) {
      $this->triggerEmailFlag($flagging, 'unflag');
    }
  }

  /**
   * Flag or unflag the corresponding `email_*` flag for `subscribe_*` flags.
   *
   * @param \Drupal\flag\FlaggingInterface $flagging
   *   The flagging object.
   * @param string $action
   *   The action. Either 'flag' or 'unflag'.
   *
   * @throws \Drupal\message_subscribe\Exception\MessageSubscribeException
   *   If there isn't a corresponding `email_` flag for the given `subscribe_`
   *   flag.
   */
  protected function triggerEmailFlag(FlaggingInterface $flagging, $action) {
    if (strpos($flagging->getFlagId(), $this->configFactory->get('message_subscribe.settings')->get('flag_prefix') . '_') === 0) {
      // The flag is a subscription flag.
      if ($flagging->getOwner()->message_subscribe_email->value || $action == 'unflag') {
        // User wants to use email for the subscription, or the subscription is
        // being removed.
        $prefix = $this->configFactory->get('message_subscribe.settings')->get('flag_prefix');
        $email_flag_name = $this->configFactory->get('message_subscribe_email.settings')->get('flag_prefix') . '_' . str_replace($prefix . '_', '', $flagging->getFlagId());
        $flag = $this->flagService->getFlagById($email_flag_name);
        if (!$flag) {
          throw new MessageSubscribeException('There is no corresponding email flag (' . $email_flag_name . ') for the ' . $flagging->getFlagId() . ' flag.');
        }
        if ($action === 'flag') {
          $this->flagService->flag($flag, $flagging->getFlaggable(), $flagging->getOwner());
        }
        elseif ($this->flagService->getFlagging($flag, $flagging->getFlaggable(), $flagging->getOwner())) {
          $this->flagService->unflag($flag, $flagging->getFlaggable(), $flagging->getOwner());
        }
      }
    }
  }

}
