<?php

namespace Drupal\mukurtu_core\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Clears the file field of an upload the server refused, and announces why.
 *
 * The browser keeps the file selected after the refusal. Core's file.js only
 * stops Save from sending it again on mousedown, so saving from the keyboard
 * sends a request over the limit, and the form reloads blank.
 *
 * The message is announced here because the message added to the page isn't
 * in a live region. Core's AnnounceCommand can't be used in this response. See
 * \Drupal\mukurtu_core\EventSubscriber\UploadTooLargeSubscriber::onException().
 *
 * @see \Drupal\mukurtu_core\Hook\ManagedFileHooks::attachResetRefusedUpload()
 */
class ResetRefusedUploadCommand implements CommandInterface {

  public function __construct(protected string $message) {}

  /**
   * {@inheritdoc}
   */
  public function render(): array {
    return [
      'command' => 'mukurtuResetRefusedUpload',
      'message' => $this->message,
    ];
  }

}
