<?php

namespace Drupal\mukurtu_core\EventSubscriber;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\PrependCommand;
use Drupal\Core\Form\Exception\BrokenPostRequestException;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\ByteSizeMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\mukurtu_core\Ajax\ResetRefusedUploadCommand;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Says plainly that an upload is too large.
 *
 * Core answers an AJAX upload over the server's limit with "An unrecoverable
 * error occurred", although the form still works and a smaller file uploads
 * normally. This answers first, with a message that says what to do.
 *
 * @see \Drupal\Core\Form\EventSubscriber\FormAjaxSubscriber::onException()
 */
class UploadTooLargeSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  public function __construct(protected MessengerInterface $messenger) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Core's FormAjaxSubscriber::onException() runs at 51.
    return [
      KernelEvents::EXCEPTION => ['onException', 52],
    ];
  }

  /**
   * Replaces the response to an AJAX upload over the server's limit.
   */
  public function onException(ExceptionEvent $event): void {
    $exception = $event->getThrowable();
    if (!$exception instanceof BrokenPostRequestException || !$event->getRequest()->query->has(FormBuilderInterface::AJAX_FORM_REQUEST)) {
      return;
    }

    $message = $this->t('This file is too large to upload. The maximum file size is @size.', [
      '@size' => ByteSizeMarkup::create((int) $exception->getSize()),
    ]);
    $this->messenger->addError($message);
    $response = new AjaxResponse(NULL, 200);
    // This request has an empty POST, so Drupal can't tell which libraries the
    // page already has, and any library added to this response is sent again
    // with everything it depends on. That reloads drupal.js, which replaces
    // the global Drupal object, so this needs commands that attach nothing.
    $response->addCommand(new ResetRefusedUploadCommand((string) $message));
    $response->addCommand(new PrependCommand(NULL, ['#type' => 'status_messages']));
    $event->allowCustomResponseCode();
    $event->setResponse($response);
  }

}
