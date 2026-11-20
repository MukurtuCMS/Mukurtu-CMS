<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_media\Kernel;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\RouteObjectInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\media_library\MediaLibraryState;
use Drupal\Tests\user\Traits\UserCreationTrait;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Tests the message that explains why someone can't add media (#559).
 *
 * Media can only be created in a cultural protocol that lets the person add
 * media. Without one, the add media pages are denied and the media library
 * has no add form; the message says why.
 */
#[Group('mukurtu_media')]
class MediaCreateNoticeTest extends MukurtuMediaTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    $this->installEntitySchema('path_alias');
    $this->installEntitySchema('flagging');
    $this->installConfig(['media_library']);
    $this->container->get('router.builder')->rebuild();
  }

  /**
   * {@inheritdoc}
   */
  protected function getProtocolStewardPermissions(): array {
    return array_merge(parent::getProtocolStewardPermissions(), ['create image media']);
  }

  /**
   * Returns the notice service.
   */
  protected function notice() {
    return $this->container->get('mukurtu_media.create_notice');
  }

  /**
   * Sends a GET request through the HTTP kernel.
   */
  protected function get(string $path) {
    return $this->container->get('http_kernel')->handle(Request::create($path));
  }

  /**
   * Returns the warnings the messenger holds, as strings.
   */
  protected function warnings(): array {
    $messages = \Drupal::messenger()->messagesByType(MessengerInterface::TYPE_WARNING);
    return array_map('strval', $messages);
  }

  /**
   * Someone whose protocol role lets them add media gets no message.
   */
  public function testMemberWhoCanAddMediaIsNotBlocked(): void {
    $this->assertFalse($this->notice()->appliesTo($this->currentUser));
    $this->assertFalse($this->notice()->appliesTo($this->currentUser, ['image']));
  }

  /**
   * The message is only about the media types the person tried to add.
   */
  public function testOnlyTheRequestedTypesCount(): void {
    $this->assertTrue($this->notice()->appliesTo($this->currentUser, ['audio', 'document']));
  }

  /**
   * People who aren't signed in get no message.
   */
  public function testAnonymousIsNotBlocked(): void {
    $this->assertFalse($this->notice()->appliesTo(new AnonymousUserSession()));
  }

  /**
   * On a site with protocols, a non-member is told to ask for access.
   */
  public function testNonMemberIsToldToAskForAccess(): void {
    $account = $this->createUser(['add community entities']);

    $this->assertTrue($this->notice()->appliesTo($account));
    $message = (string) $this->notice()->getMessage($account);
    $this->assertStringContainsString('Ask a community manager or protocol steward for access.', $message);
    $this->assertStringNotContainsString('/communities/community/add', $message);
  }

  /**
   * On a site with no protocols, someone who can add a community gets a link.
   */
  public function testNoProtocolsLinksToAddCommunity(): void {
    $this->protocol->delete();
    $account = $this->createUser(['add community entities']);

    $message = (string) $this->notice()->getMessage($account);
    $this->assertStringContainsString('this site doesn\'t have one yet', $message);
    $this->assertStringContainsString('href="/communities/community/add"', $message);
  }

  /**
   * With no protocols, someone who can't add a community gets no link.
   */
  public function testNoProtocolsWithoutCommunityPermission(): void {
    $this->protocol->delete();
    $account = $this->createUser();

    $message = (string) $this->notice()->getMessage($account);
    $this->assertStringContainsString('Ask a community manager or protocol steward for access.', $message);
  }

  /**
   * The add media page is still denied; only the message is new.
   */
  public function testAddPageIsStillDenied(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->assertSame(403, $this->get('/admin/media/add')->getStatusCode());
    $this->assertSame(403, $this->get('/admin/media/add/image')->getStatusCode());
  }

  /**
   * The denied add media page explains why.
   */
  public function testAddPageExplainsAccessDenied(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->dispatchAccessDenied('entity.media.add_page');

    $this->assertStringContainsString('Ask a community manager or protocol steward for access.', implode(' ', $this->warnings()));
  }

  /**
   * The denied add form for one media type explains why.
   */
  public function testAddFormExplainsAccessDenied(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->dispatchAccessDenied('entity.media.add_form', ['media_type' => 'image']);

    $this->assertStringContainsString('Ask a community manager or protocol steward for access.', implode(' ', $this->warnings()));
  }

  /**
   * Other denied pages get no message about media.
   */
  public function testOtherDeniedPagesAreUnchanged(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->dispatchAccessDenied('entity.user.collection');

    $this->assertSame([], $this->warnings());
  }

  /**
   * A denied request for something other than an HTML page gets no message.
   */
  public function testNonHtmlRequestsAreUnchanged(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $this->dispatchAccessDenied('entity.media.add_page', [], 'json');

    $this->assertSame([], $this->warnings());
  }

  /**
   * Dispatches an access denied exception for a route.
   *
   * Through the HTTP kernel, core goes on to render the access denied page,
   * which takes the message out of the messenger before the test can look.
   * Calling the subscriber directly leaves it there.
   */
  protected function dispatchAccessDenied(string $route_name, array $parameters = [], string $format = 'html'): void {
    $route = $this->container->get('router.route_provider')->getRouteByName($route_name);
    $request = Request::create($route->getPath());
    $request->setRequestFormat($format);
    $request->attributes->add([
      RouteObjectInterface::ROUTE_NAME => $route_name,
      RouteObjectInterface::ROUTE_OBJECT => $route,
      '_raw_variables' => new InputBag($parameters),
    ] + $parameters);
    $this->container->get('request_stack')->push($request);

    $event = new ExceptionEvent($this->container->get('http_kernel'), $request, HttpKernelInterface::MAIN_REQUEST, new AccessDeniedHttpException());
    \Drupal::service('mukurtu_media.media_add_access_denied_subscriber')->onException($event);

    $this->container->get('request_stack')->pop();
  }

  /**
   * The media library explains why it has no add form.
   */
  public function testMediaLibraryExplainsMissingAddForm(): void {
    $this->setCurrentUser($this->createUser(['access content']));

    $build = $this->container->get('media_library.ui_builder')->buildUi($this->libraryState());

    $this->assertArrayHasKey('media_create_notice', $build['content']);
  }

  /**
   * The media library has no message for someone who can add media.
   */
  public function testMediaLibraryHasNoMessageForMember(): void {
    $this->setCurrentUser($this->currentUser);

    $build = $this->container->get('media_library.ui_builder')->buildUi($this->libraryState());

    $this->assertArrayNotHasKey('media_create_notice', $build['content']);
  }

  /**
   * Returns a media library state for the image and audio types.
   */
  protected function libraryState(): MediaLibraryState {
    return MediaLibraryState::create('media_library.opener.field_widget', ['image', 'audio'], 'image', 1, [
      'entity_type_id' => 'taxonomy_term',
      'bundle' => 'category',
      'field_name' => 'field_category_image',
    ]);
  }

}
