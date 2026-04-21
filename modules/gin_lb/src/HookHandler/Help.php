<?php

declare(strict_types=1);

namespace Drupal\gin_lb\HookHandler;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\filter\FilterPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Hook implementation.
 */
class Help implements ContainerInjectionInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected ModuleExtensionList $moduleExtensionList;

  /**
   * The filter plugin manager.
   *
   * @var \Drupal\filter\FilterPluginManager|null
   */
  protected ?FilterPluginManager $filterPluginManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $moduleExtensionList
   *   The module extension list.
   * @param \Drupal\filter\FilterPluginManager|null $filterPluginManager
   *   The filter plugin manager.
   */
  public function __construct(
    ModuleHandlerInterface $moduleHandler,
    ConfigFactoryInterface $configFactory,
    ModuleExtensionList $moduleExtensionList,
    ?FilterPluginManager $filterPluginManager,
  ) {
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $configFactory;
    $this->moduleExtensionList = $moduleExtensionList;
    $this->filterPluginManager = $filterPluginManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    // @phpstan-ignore-next-line
    return new static(
      $container->get('module_handler'),
      $container->get('config.factory'),
      $container->get('extension.list.module'),
      $container->get('plugin.manager.filter', ContainerInterface::NULL_ON_INVALID_REFERENCE),
    );
  }

  /**
   * Hook implementation.
   *
   * @param string $routeName
   *   For page-specific help, use the route name as identified in the
   *   module's routing.yml file. For module overview help, the route name
   *   will be in the form of "help.page.$modulename".
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match. This can be used to generate different help
   *   output for different pages that share the same route.
   *
   * @return string|array
   *   A render array, localized string, or object that can be rendered into
   *   a string, containing the help text.
   */
  public function help(string $routeName, RouteMatchInterface $routeMatch): array|string {
    switch ($routeName) {
      case 'help.page.gin_lb':
        $text = \file_get_contents($this->moduleExtensionList->getPath('gin_lb') . '/README.md');
        if (!$this->moduleHandler->moduleExists('markdown')) {
          return '<pre>' . $text . '</pre>';
        }

        if (!$this->filterPluginManager) {
          return '<pre>' . $text . '</pre>';
        }

        // Use the Markdown filter to render the README.
        $settings = $this->configFactory->get('markdown.settings')->getRawData();
        $config = ['settings' => $settings];
        $filter = $this->filterPluginManager->createInstance('markdown', $config);
        // @phpstan-ignore-next-line
        return $filter->process($text, 'en');
    }
    return '';
  }

}
