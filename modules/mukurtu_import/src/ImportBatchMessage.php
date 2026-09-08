<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\migrate\MigrateMessage;

/**
 * A migrate message that also collects error-level text for later display.
 *
 * MigrateMessage::display() only ever reaches the 'migrate' watchdog
 * channel, so a total migration failure (e.g. a source plugin exception at
 * rewind()) leaves no trace anywhere the batch import UI can surface to the
 * person who ran the import. This collects that text so
 * ImportBatchExecutable::batchProcessImportDefinition() can feed it into the
 * batch results alongside row-level idMap messages.
 */
class ImportBatchMessage extends MigrateMessage {

  /**
   * Error-level messages displayed during the import, file/line stripped.
   *
   * @var string[]
   */
  protected array $errorMessages = [];

  /**
   * {@inheritdoc}
   */
  public function display($message, $type = 'status') {
    parent::display($message, $type);
    if ($type === 'error') {
      // Strip the " in /path/to/File.php line 123" suffix MigrateExecutable
      // appends to source/pipeline exception messages -- that detail stays
      // in the watchdog log (written above), but isn't meaningful to a
      // site's import users.
      $text = preg_replace('/\s+in\s+\S+\.php line \d+$/', '', (string) $message);
      $this->errorMessages[] = $text;
    }
  }

  /**
   * Gets the error-level messages displayed so far.
   *
   * @return string[]
   */
  public function getErrorMessages(): array {
    return $this->errorMessages;
  }

}
