<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\ThemeHooks;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * Tests suppressing Klaro's toggle button inside entity browser modals.
 *
 * @see \Drupal\mukurtu_core\Hook\ThemeHooks::pageAttachmentsAlter()
 */
#[Group('mukurtu_core')]
class ThemeHooksPageAttachmentsAlterTest extends KernelTestBase {

  /**
   * Pushes a request matched to the given route name onto the stack.
   */
  protected function setCurrentRoute(string $routeName): void {
    $request = Request::create('/entity-browser/modal/' . $routeName);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $routeName);
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, new Route('/entity-browser/modal/' . $routeName));
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Sets the current user to a mock account with exactly the given permissions.
   */
  protected function setCurrentUserPermissions(array $permissions): void {
    $account = $this->createMock(AccountInterface::class);
    $account->method('hasPermission')->willReturnCallback(
      static fn (string $permission): bool => in_array($permission, $permissions, TRUE)
    );
    $this->container->get('current_user')->setAccount($account);
  }

  /**
   * Builds a minimal Klaro services list for use in test attachments.
   */
  protected function klaroServices(): array {
    return [
      ['name' => 'toastify'],
      ['name' => 'soundcloud'],
    ];
  }

  /**
   * The toggle button is suppressed on entity browser modal routes.
   */
  public function testToggleButtonSuppressedOnEntityBrowserRoute(): void {
    $this->setCurrentRoute('entity_browser.mukurtu_content_browser');

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['show_toggle_button' => TRUE],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $this->assertFalse($attachments['#attached']['drupalSettings']['klaro']['show_toggle_button']);
  }

  /**
   * The toggle button is left alone on a normal page route.
   */
  public function testToggleButtonUntouchedOnNormalRoute(): void {
    $this->setCurrentRoute('entity.node.canonical');

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['show_toggle_button' => TRUE],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $this->assertTrue($attachments['#attached']['drupalSettings']['klaro']['show_toggle_button']);
  }

  /**
   * Nothing errors when Klaro hasn't attached its settings at all.
   */
  public function testNoErrorWhenKlaroSettingsAbsent(): void {
    $this->setCurrentRoute('entity_browser.mukurtu_content_browser');

    $attachments = ['#attached' => []];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $this->assertArrayNotHasKey('drupalSettings', $attachments['#attached']);
  }

  /**
   * Toastify is removed for a user with no Layout Builder permissions.
   */
  public function testToastifyRemovedWithoutLayoutBuilderAccess(): void {
    $this->setCurrentRoute('entity.node.canonical');
    $this->setCurrentUserPermissions([]);

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['config' => ['services' => $this->klaroServices()]],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $names = array_column($attachments['#attached']['drupalSettings']['klaro']['config']['services'], 'name');
    $this->assertNotContains('toastify', $names);
    $this->assertContains('soundcloud', $names);
    $this->assertContains('user.permissions', $attachments['#cache']['contexts']);
  }

  /**
   * Toastify stays for a user with 'configure any layout'.
   */
  public function testToastifyKeptWithConfigureAnyLayout(): void {
    $this->setCurrentRoute('entity.node.canonical');
    $this->setCurrentUserPermissions(['configure any layout']);

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['config' => ['services' => $this->klaroServices()]],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $names = array_column($attachments['#attached']['drupalSettings']['klaro']['config']['services'], 'name');
    $this->assertContains('toastify', $names);
  }

  /**
   * Toastify stays for a manager with a bundle-specific override permission.
   */
  public function testToastifyKeptWithPageOverridePermission(): void {
    $this->setCurrentRoute('entity.node.canonical');
    $this->setCurrentUserPermissions(['configure editable page node layout overrides']);

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['config' => ['services' => $this->klaroServices()]],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $names = array_column($attachments['#attached']['drupalSettings']['klaro']['config']['services'], 'name');
    $this->assertContains('toastify', $names);
  }

  /**
   * Nothing errors when Klaro's services list is absent.
   */
  public function testNoErrorWhenKlaroServicesAbsent(): void {
    $this->setCurrentRoute('entity.node.canonical');

    $attachments = [
      '#attached' => [
        'drupalSettings' => [
          'klaro' => ['show_toggle_button' => TRUE],
        ],
      ],
    ];
    (new ThemeHooks())->pageAttachmentsAlter($attachments);

    $this->assertArrayNotHasKey('config', $attachments['#attached']['drupalSettings']['klaro']);
  }

}
