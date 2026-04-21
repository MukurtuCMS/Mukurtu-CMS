<?php

namespace Drupal\message_subscribe_ui\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Utility\Error;
use Drupal\flag\FlagInterface;
use Drupal\flag\FlagServiceInterface;
use Drupal\message_subscribe\Exception\MessageSubscribeException;
use Drupal\message_subscribe\SubscribersInterface;
use Drupal\user\UserInterface;
use Drupal\views\Views;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Default controller for the message_subscribe_ui module.
 */
final class SubscriptionController extends ControllerBase {

  /**
   * The message subscribe settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * The flag service.
   *
   * @var \Drupal\flag\FlagServiceInterface
   */
  protected $flagService;

  /**
   * The message subscribers service.
   *
   * @var \Drupal\message_subscribe\SubscribersInterface
   */
  protected $subscribers;

  /**
   * The logger channel service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Construct the subscriptions controller.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\flag\FlagServiceInterface $flag_service
   *   The flag service manager.
   * @param \Drupal\message_subscribe\SubscribersInterface $subscribers
   *   The message subscribers service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger channel service.
   */
  public function __construct(AccountProxyInterface $current_user, FlagServiceInterface $flag_service, SubscribersInterface $subscribers, ConfigFactoryInterface $config_factory, LoggerInterface $logger) {
    $this->currentUser = $current_user;
    $this->flagService = $flag_service;
    $this->subscribers = $subscribers;
    $this->config = $config_factory->get('message_subscribe.settings');
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new self(
      $container->get('current_user'),
      $container->get('flag'),
      $container->get('message_subscribe.subscribers'),
      $container->get('config.factory'),
      $container->get('logger.channel.message_subscribe')
    );
  }

  /**
   * Access controller for subscription management tabs.
   *
   * @param \Drupal\Core\Session\AccountInterface $user
   *   The user account session.
   * @param \Drupal\flag\FlagInterface $flag
   *   (optional) The flag for which to display the view.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   Returns TRUE if access is granted.
   */
  public function tabAccess(AccountInterface $user, ?FlagInterface $flag = NULL) {
    if (!$flag) {
      // We are inside /message-subscribe so get the first flag.
      $flags = $this->subscribers->getFlags();
      $flag = reset($flags);
    }

    if (!$flag) {
      // No flag, or flag is disabled.
      return AccessResult::forbidden();
    }

    if (!$flag->status()) {
      // The flag is disabled.
      return AccessResult::forbidden();
    }

    if ($this->currentUser->hasPermission('administer message subscribe')) {
      return AccessResult::allowed();
    }

    if (!$flag->actionAccess('unflag', $user) || $user->id() != $this->currentUser->id()) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowed();
  }

  /**
   * Provides the page title for a given tab.
   *
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag for which to display subscriptions.
   *
   * @return string
   *   The tab title as defined by the flag.
   */
  public function tabTitle(FlagInterface $flag) {
    return $flag->label();
  }

  /**
   * Render the subscription management tab.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag to display subscriptions for.
   *
   * @return array
   *   A render array.
   */
  public function tab(UserInterface $user, ?FlagInterface $flag = NULL) {
    if (!$flag) {
      // We are inside /message-subscribe so get the first flag.
      $flags = $this->subscribers->getFlags();
      $flag = reset($flags);
    }
    $result = [];
    try {
      $view = $this->getView($user, $flag);
      $result = $view->preview();
      $result['#cache']['tags'] = $flag->getCacheTags() + $view->getCacheTags();
    }
    catch (MessageSubscribeException $e) {
      if (version_compare(\Drupal::VERSION, '10.1.0', '<')) {
        // @phpstan-ignore-next-line
        watchdog_exception('message_subscribe_ui', $e);
      }
      else {
        Error::logException($this->logger, $e);
      }

      $result['#markup'] = $this->t('There was an exception displaying the subscriptions for this user. View recently logged messages for more information.');
    }

    return $result;
  }

  /**
   * Helper function to get a view associated with a flag.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user to pass in as the views argument.
   * @param \Drupal\flag\FlagInterface $flag
   *   The flag for which to find a matching view.
   *
   * @return \Drupal\views\ViewExecutable
   *   The corresponding view executable.
   *
   * @throws \Drupal\message_subscribe\Exception\MessageSubscribeException
   *   - If a view corresponding to the `subscribe_ENTITY_TYPE_ID` does not
   *     exist.
   *   - If the view's relationship flag isn't properly enabled or configured.
   */
  protected function getView(UserInterface $account, FlagInterface $flag) {

    $entity_type = $flag->getFlaggableEntityTypeId();

    $prefix = $this->config->get('flag_prefix');
    // View name + display ID.
    $default_view_name = $prefix . '_' . $entity_type . ':default';
    [$view_name, $display_id] = explode(':', $flag->getThirdPartySetting('message_subscribe_ui', 'view_name', $default_view_name));

    if (!$view = Views::getView($view_name)) {
      // View doesn't exist.
      throw new MessageSubscribeException('View "' . $view_name . '" does not exist.');
    }

    $view->setDisplay($display_id);
    $view->setArguments([$account->id()]);

    // Change the flag's relationship to point to our flag.
    $relationships = $view->display_handler->getOption('relationships');
    foreach ($relationships as $key => $relationship) {
      if (strpos($key, 'flag_') !== 0) {
        // Not a flag relationship.
        continue;
      }

      // Check that the flag is valid.
      $rel_flag = $this->flagService->getFlagById($relationship['flag']);
      if (!$rel_flag || (!$rel_flag->status())) {
        throw new MessageSubscribeException('Flag "' . $relationship['flag'] . '" is not setup correctly. It is probably disabled or have no bundles configured.');
      }
    }

    return $view;
  }

}
