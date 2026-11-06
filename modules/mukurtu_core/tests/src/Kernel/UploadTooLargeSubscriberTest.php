<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\EventSubscriber\FormAjaxSubscriber;
use Drupal\Core\Form\Exception\BrokenPostRequestException;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\EventSubscriber\UploadTooLargeSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Tests the message for an AJAX upload over the server's limit.
 */
#[CoversClass(UploadTooLargeSubscriber::class)]
#[Group('mukurtu_core')]
class UploadTooLargeSubscriberTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system'];

  /**
   * Sends an exception through the subscriber.
   */
  protected function dispatch(\Throwable $exception, array $query): ExceptionEvent {
    $event = new ExceptionEvent(
      $this->container->get('http_kernel'),
      Request::create('/', 'POST', $query),
      HttpKernelInterface::MAIN_REQUEST,
      $exception,
    );
    // Query parameters of a POST request, as an AJAX upload sends them.
    $event->getRequest()->query->replace($query);
    (new UploadTooLargeSubscriber($this->container->get('messenger')))->onException($event);
    return $event;
  }

  /**
   * Tests that an AJAX upload over the limit gets the plain message.
   */
  public function testAjaxUploadOverTheLimit(): void {
    $event = $this->dispatch(new BrokenPostRequestException(2097152), [FormBuilderInterface::AJAX_FORM_REQUEST => 1]);

    $response = $event->getResponse();
    $this->assertInstanceOf(AjaxResponse::class, $response);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($event->isAllowingCustomResponseCode());
    $this->assertTrue($event->isPropagationStopped(), "Core's message must not be added as well.");

    // The response renders the messages, which takes them off the messenger.
    $commands = json_encode($response->getCommands());
    $this->assertStringContainsString('This file is too large to upload. The maximum file size is 2 MB.', $commands);
    $this->assertStringNotContainsString('unrecoverable', $commands);
  }

  /**
   * Tests that other requests and exceptions are left alone.
   */
  public function testOtherRequestsAreLeftAlone(): void {
    $not_ajax = $this->dispatch(new BrokenPostRequestException(1024), []);
    $other_exception = $this->dispatch(new \RuntimeException('Other'), [FormBuilderInterface::AJAX_FORM_REQUEST => 1]);

    $this->assertNull($not_ajax->getResponse());
    $this->assertNull($other_exception->getResponse());
    $this->assertSame([], $this->container->get('messenger')->messagesByType('error'));
  }

  /**
   * Tests that the subscriber is registered ahead of core's.
   */
  public function testRunsBeforeCore(): void {
    $core = FormAjaxSubscriber::getSubscribedEvents();
    $ours = UploadTooLargeSubscriber::getSubscribedEvents();
    $this->assertGreaterThan($core[KernelEvents::EXCEPTION][1], $ours[KernelEvents::EXCEPTION][1]);
  }

}
