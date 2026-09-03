<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_submissions\Kernel;

use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_submissions\Theme\PublicSubmissionEntityBrowserThemeNegotiator;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Route;

/**
 * Tests PublicSubmissionEntityBrowserThemeNegotiator.
 *
 * @see \Drupal\mukurtu_submissions\Theme\PublicSubmissionEntityBrowserThemeNegotiator
 */
#[Group('mukurtu_submissions')]
class PublicSubmissionEntityBrowserThemeNegotiatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'mukurtu_submissions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installConfig(['system']);
  }

  /**
   * Pushes a request onto the stack matched to the given route/query.
   */
  protected function pushRequest(string $routeName, array $query = []): \Drupal\Core\Routing\RouteMatchInterface {
    $request = Request::create('/entity-browser/modal/test', 'GET', $query);
    $request->attributes->set(RouteObjectInterface::ROUTE_NAME, $routeName);
    $route = new Route('/entity-browser/modal/test');
    $request->attributes->set(RouteObjectInterface::ROUTE_OBJECT, $route);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
    return \Drupal\Core\Routing\RouteMatch::createFromRequest($request);
  }

  protected function negotiator(): PublicSubmissionEntityBrowserThemeNegotiator {
    return $this->container->get('mukurtu_submissions.public_submission_entity_browser_theme_negotiator');
  }

  /**
   * Applies when opened from a public submission page.
   */
  public function testAppliesWhenOpenedFromSubmissionPage(): void {
    $route_match = $this->pushRequest('entity_browser.mukurtu_content_browser', [
      'original_path' => '/submit/node/collection',
    ]);
    $this->assertTrue($this->negotiator()->applies($route_match));
  }

  /**
   * Forces the front-end default theme when it applies.
   */
  public function testDeterminesFrontEndTheme(): void {
    \Drupal::configFactory()->getEditable('system.theme')
      ->set('default', 'mukurtu_v4')
      ->set('admin', 'gin')
      ->save();

    $route_match = $this->pushRequest('entity_browser.mukurtu_content_browser', [
      'original_path' => '/submit/node/collection',
    ]);
    $this->assertEquals('mukurtu_v4', $this->negotiator()->determineActiveTheme($route_match));
  }

  /**
   * Does not apply when opened from a normal admin content-editing page.
   */
  public function testDoesNotApplyFromAdminPage(): void {
    $route_match = $this->pushRequest('entity_browser.mukurtu_content_browser', [
      'original_path' => '/node/add/digital_heritage',
    ]);
    $this->assertFalse($this->negotiator()->applies($route_match));
  }

  /**
   * Does not apply to a non-entity-browser route, even with original_path.
   */
  public function testDoesNotApplyToNonEntityBrowserRoute(): void {
    $route_match = $this->pushRequest('entity.node.canonical', [
      'original_path' => '/submit/node/collection',
    ]);
    $this->assertFalse($this->negotiator()->applies($route_match));
  }

  /**
   * Does not apply when original_path is absent entirely.
   */
  public function testDoesNotApplyWithoutOriginalPath(): void {
    $route_match = $this->pushRequest('entity_browser.mukurtu_content_browser');
    $this->assertFalse($this->negotiator()->applies($route_match));
  }

}
