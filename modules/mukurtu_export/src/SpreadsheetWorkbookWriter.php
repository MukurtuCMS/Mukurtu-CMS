<?php

declare(strict_types=1);

namespace Drupal\mukurtu_export;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use League\Csv\Reader;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Psr\Log\LoggerInterface;

/**
 * Combines the CSV files from an export into one XLSX or ODS workbook.
 *
 * The exporter writes CSV across many batch requests because a CSV file can
 * be appended to. A workbook can't, so it is built once at the end, with one
 * sheet per CSV file.
 */
class SpreadsheetWorkbookWriter {

  /**
   * The workbook formats this writer supports, keyed by file extension.
   */
  public const FORMATS = ['xlsx', 'ods'];

  /**
   * Excel's limit on the number of characters in one cell.
   */
  public const XLSX_MAX_CELL_LENGTH = 32767;

  /**
   * The longest sheet name XLSX and ODS allow.
   */
  protected const MAX_SHEET_NAME_LENGTH = 31;

  /**
   * Result of write(): the workbook was written.
   */
  public const WRITTEN = 'written';

  /**
   * Result of write(): a value is too long for an XLSX cell.
   */
  public const TOO_LONG = 'too_long';

  /**
   * Result of write(): writing failed for another reason, which is logged.
   */
  public const FAILED = 'failed';

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Writes CSV files into a workbook, one sheet each.
   *
   * @param array $csvFiles
   *   Export deliverables, each with a 'uri' and an 'entryname' ending in
   *   ".csv". The entry name, without the extension, names the sheet.
   * @param string $format
   *   One of self::FORMATS.
   * @param string $destination
   *   The stream wrapper URI to write the workbook to.
   * @param array $csvSettings
   *   The 'separator', 'enclosure', and 'escape' the CSV files were written
   *   with.
   *
   * @return string
   *   self::WRITTEN, self::TOO_LONG if a value is over Excel's cell limit,
   *   or self::FAILED if writing failed for another reason, which is
   *   logged. Unless the workbook was written, nothing is left at
   *   $destination.
   */
  public function write(array $csvFiles, string $format, string $destination, array $csvSettings): string {
    if (!in_array($format, self::FORMATS, TRUE)) {
      return self::FAILED;
    }

    // Workbooks are zip files, which need a local path to write. Build the
    // workbook in the system temporary directory, then move it into place.
    $path = tempnam(sys_get_temp_dir(), 'mukurtu_export_');
    if ($path === FALSE) {
      $this->logger->error('Unable to create a temporary file for the export workbook.');
      return self::FAILED;
    }

    $writer = $format === 'ods' ? new OdsWriter() : new XlsxWriter();
    $tooLong = FALSE;
    try {
      $writer->openToFile($path);
      $usedNames = [];
      foreach (array_values($csvFiles) as $index => $csvFile) {
        if ($index > 0) {
          $writer->addNewSheetAndMakeItCurrent();
        }
        $writer->getCurrentSheet()->setName($this->sheetName($csvFile['entryname'] ?? '', $usedNames));
        foreach ($this->readCsv($csvFile['uri'], $csvSettings) as $record) {
          // ODS has no cell length limit. Checking here, rather than in a
          // separate pass, reads each CSV file only once.
          if ($format === 'xlsx' && $this->hasOverlongValue($record)) {
            $tooLong = TRUE;
            throw new \LengthException('A value is too long for an XLSX cell.');
          }
          // Every value is written as text so IDs, dates, and numbers come
          // back exactly as exported instead of being reformatted.
          $writer->addRow(new Row(array_map(fn ($value) => new StringCell((string) $value, NULL), $record)));
        }
      }
      $writer->close();
      $this->fileSystem->move($path, $destination, FileExists::Replace);
    }
    catch (\Throwable $e) {
      if (!$tooLong) {
        $this->logger->error('Unable to write the export workbook: @message', ['@message' => $e->getMessage()]);
      }
      try {
        $writer->close();
      }
      catch (\Throwable) {
        // The writer may already be closed; the file is deleted below.
      }
      @unlink($path);
      return $tooLong ? self::TOO_LONG : self::FAILED;
    }
    return self::WRITTEN;
  }

  /**
   * Whether any value in a CSV record is too long for an XLSX cell.
   */
  protected function hasOverlongValue(array $record): bool {
    foreach ($record as $value) {
      if (mb_strlen((string) $value) > self::XLSX_MAX_CELL_LENGTH) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Reads every record of a CSV file, header included.
   *
   * @return iterable
   *   The records as arrays of strings.
   */
  protected function readCsv(string $uri, array $csvSettings): iterable {
    $reader = Reader::from($uri, 'r');
    $reader->setDelimiter($csvSettings['separator'] ?? ',');
    $reader->setEnclosure($csvSettings['enclosure'] ?? '"');
    $reader->setEscape($csvSettings['escape'] ?? '\\');
    return $reader->getRecords();
  }

  /**
   * Builds a valid, unique sheet name from a CSV entry name.
   *
   * @param string $entryName
   *   The CSV file's name in the export package.
   * @param array $usedNames
   *   Lower-cased names already used in this workbook. Updated in place.
   */
  public function sheetName(string $entryName, array &$usedNames): string {
    $name = preg_replace('/\.csv$/i', '', $entryName) ?? '';
    // Characters XLSX and ODS forbid in sheet names.
    $name = preg_replace('/[\\\\\/?*:\[\]]+/u', '-', $name) ?? '';
    // A name can't start or end with an apostrophe.
    $name = trim($name, "' ");
    if ($name === '') {
      $name = 'Sheet';
    }

    $candidate = mb_substr($name, 0, self::MAX_SHEET_NAME_LENGTH);
    $suffix = 2;
    while (in_array(mb_strtolower($candidate), $usedNames, TRUE)) {
      $tail = " ($suffix)";
      $candidate = rtrim(mb_substr($name, 0, self::MAX_SHEET_NAME_LENGTH - mb_strlen($tail)), "' ") . $tail;
      $suffix++;
    }
    $usedNames[] = mb_strtolower($candidate);
    return $candidate;
  }

}
