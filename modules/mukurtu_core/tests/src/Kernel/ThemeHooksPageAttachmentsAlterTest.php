<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
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

}
