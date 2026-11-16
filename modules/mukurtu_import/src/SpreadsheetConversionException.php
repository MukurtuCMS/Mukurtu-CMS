<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Thrown when an uploaded workbook cannot be converted to CSV.
 *
 * Carries a translatable message that is safe to show to the person who
 * uploaded the file.
 */
class SpreadsheetConversionException extends \RuntimeException {

  public function __construct(
    protected TranslatableMarkup $userMessage,
    int $code = 0,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($userMessage->getUntranslatedString(), $code, $previous);
  }

  /**
   * The message to show the user.
   */
  public function getUserMessage(): TranslatableMarkup {
    return $this->userMessage;
  }

}
