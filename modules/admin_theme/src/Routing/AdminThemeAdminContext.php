<?php

namespace Drupal\admin_theme\Routing;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Executable\ExecutableManagerInterface;
use Drupal\Core\Routing\AdminContext;
use Symfony\Component\Routing\Route;

/**
 * Decorates the core AdminContext to check custom admin paths.
 *
 * @todo Do not extend the decorated class, implement an interface after
 *   https://www.drupal.org/node/2708599.
 */
class AdminThemeAdminContext extends AdminContext {

  /**
   * The decorated admin context service.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $subject;

  /**
   * The condition manager.
   *
   * @var \Drupal\Core\Executable\ExecutableManagerInterface
   */
  protected $conditionManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * AdminThemeAdminContext constructor.
   *
   * @param \Drupal\Core\Routing\AdminContext $subject
   *   The decorated admin context service.
   * @param \Drupal\Core\Executable\ExecutableManagerInterface $condition_manager
   *   The condition manager.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   */
  public function __construct(AdminContext $subject, ExecutableManagerInterface $condition_manager, ConfigFactoryInterface $config_factory) {
    $this->subject = $subject;
    $this->conditionManager = $condition_manager;
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public function isAdminRoute(Route $route = NULL) {
    $excludePaths = (string) $this->configFactory->get('admin_theme.settings')->get('exclude_paths');
    /** @var \Drupal\Core\Condition\ConditionInterface $excludeCondition */
    $excludeCondition = $this->conditionManager->createInstance('request_path', ['pages' => $excludePaths]);
    if ($this->conditionManager->execute($excludeCondition)) {
      return FALSE;
    }

    $paths = $this->configFactory->get('admin_theme.settings')->get('paths');
    /** @var \Drupal\Core\Condition\ConditionInterface $condition */
    $condition = $this->conditionManager->createInstance('request_path', ['pages' => $paths]);
    if ($this->conditionManager->execute($condition)) {
      return TRUE;
    }

    return $this->subject->isAdminRoute($route);
  }

}
