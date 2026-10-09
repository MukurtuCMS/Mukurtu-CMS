<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Form\EnableMultilingualForm;
use Drupal\mukurtu_core\Menu\EnableMultilingualMenuLink;
use PHPUnit\Framework\Attributes\Group;
use Psr\Log\NullLogger;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests the dashboard's "Enable multilingual" link and its form (#2374).
 *
 * The form and link are built directly, with the module installer mocked,
 * because installing mukurtu_multilingual for real needs most of the profile.
 * The route and menu wiring are asserted against the shipped YAML.
 */
#[Group('mukurtu_core')]
class EnableMultilingualTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  /**
   * Builds the form with the given module installer.
   */
  protected function form(ModuleInstallerInterface $installer): EnableMultilingualForm {
    $form = new EnableMultilingualForm($installer, \Drupal::moduleHandler(), new NullLogger());
    $form->setStringTranslation(\Drupal::service('string_translation'));
    $form->setMessenger(\Drupal::messenger());
    return $form;
  }

  /**
   * Builds the dashboard menu link plugin.
   */
  protected function menuLink(): EnableMultilingualMenuLink {
    $definition = $this->linksMenuYaml()['mukurtu_core.enable_multilingual'] + [
      'id' => 'mukurtu_core.enable_multilingual',
      'provider' => 'mukurtu_core',
      'route_parameters' => [],
      'options' => [],
      'metadata' => [],
      'enabled' => TRUE,
    ];
    return EnableMultilingualMenuLink::create($this->container, [], 'mukurtu_core.enable_multilingual', $definition);
  }

  /**
   * Makes mukurtu_multilingual count as installed, without installing it.
   */
  protected function fakeMultilingualInstalled(): void {
    $path = \Drupal::service('extension.list.module')->getPath(EnableMultilingualForm::MODULE);
    \Drupal::moduleHandler()->addModule(EnableMultilingualForm::MODULE, $path);
  }

  /**
   * Parses the module's links.menu.yml.
   */
  protected function linksMenuYaml(): array {
    return Yaml::parseFile(dirname(__DIR__, 3) . '/mukurtu_core.links.menu.yml');
  }

  /**
   * The link shows until mukurtu_multilingual is installed, then hides.
   */
  public function testMenuLinkHidesOnceInstalled(): void {
    $this->assertTrue($this->menuLink()->isEnabled());
    $this->assertContains('config:core.extension', $this->menuLink()->getCacheTags());

    $this->fakeMultilingualInstalled();
    $this->assertFalse($this->menuLink()->isEnabled());
  }

  /**
   * The form is reachable until mukurtu_multilingual is installed.
   */
  public function testAccessDeniedOnceInstalled(): void {
    $form = $this->form($this->createMock(ModuleInstallerInterface::class));
    $access = $form->access();
    $this->assertTrue($access->isAllowed());
    $this->assertContains('config:core.extension', $access->getCacheTags());

    $this->fakeMultilingualInstalled();
    $this->assertFalse($form->access()->isAllowed());
  }

  /**
   * Confirming installs the module and goes to Manage site languages.
   */
  public function testSubmitInstallsModule(): void {
    $installer = $this->createMock(ModuleInstallerInterface::class);
    $installer->expects($this->once())
      ->method('install')
      ->with([EnableMultilingualForm::MODULE])
      ->willReturn(TRUE);

    $form_state = new FormState();
    $form_array = [];
    $this->form($installer)->submitForm($form_array, $form_state);

    $this->assertSame('entity.configurable_language.collection', $form_state->getRedirect()->getRouteName());
    $messages = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_STATUS);
    $this->assertCount(1, $messages);
    $this->assertSame('Multilingual features are enabled. Add a language to start translating.', (string) $messages[0]);
  }

  /**
   * A failed install shows an error and returns to the dashboard.
   */
  public function testSubmitReportsInstallFailure(): void {
    $installer = $this->createMock(ModuleInstallerInterface::class);
    $installer->method('install')->willThrowException(new \RuntimeException('Boom'));

    $form_state = new FormState();
    $form_array = [];
    $this->form($installer)->submitForm($form_array, $form_state);

    $redirect = $form_state->getRedirect();
    $this->assertSame('entity.dashboard.canonical', $redirect->getRouteName());
    $this->assertSame(['dashboard' => 'mukurtu_dashboard'], $redirect->getRouteParameters());
    $this->assertSame([], \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_STATUS));
    $errors = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_ERROR);
    $this->assertCount(1, $errors);
    $this->assertSame('Multilingual features could not be enabled. Check the site log for details.', (string) $errors[0]);
  }

  /**
   * Pins the link to the Multilingual section and its route to admins.
   */
  public function testShippedWiring(): void {
    $link = $this->linksMenuYaml()['mukurtu_core.enable_multilingual'];
    $this->assertSame('dashboard-multilingual', $link['menu_name']);
    $this->assertSame(EnableMultilingualMenuLink::class, $link['class']);

    $routes = Yaml::parseFile(dirname(__DIR__, 3) . '/mukurtu_core.routing.yml');
    $this->assertArrayHasKey($link['route_name'], $routes);
    $route = $routes[$link['route_name']];
    $this->assertSame('\\' . EnableMultilingualForm::class, $route['defaults']['_form']);
    $this->assertSame('administer modules', $route['requirements']['_permission']);
    $this->assertSame('\\' . EnableMultilingualForm::class . '::access', $route['requirements']['_custom_access']);
  }

}
