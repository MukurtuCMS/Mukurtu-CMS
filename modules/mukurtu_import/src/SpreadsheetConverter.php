<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\FileInterface;
use League\Csv\Writer;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\ErrorCell;
use OpenSpout\Common\Entity\Cell\FormulaCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\ReaderInterface;
use OpenSpout\Reader\SheetInterface;
use OpenSpout\Reader\SheetWithVisibilityInterface;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

/**
 * Converts uploaded XLSX and ODS workbooks into UTF-8 CSV files.
 *
 * The import pipeline (header reads, import strategies, and the
 * migrate_source_csv source plugin) only understands CSV, so workbooks are
 * converted when they are uploaded and everything downstream sees ordinary
 * CSV files. Each visible sheet with a header row becomes one CSV file.
 */
class SpreadsheetConverter {

  use StringTranslationTrait;

  /**
   * File extensions this converter accepts.
   */
  public const EXTENSIONS = ['xlsx', 'ods'];

  /**
   * The most sheets a single workbook may contain.
   */
  public const MAX_SHEETS = 100;

  /**
   * The most data rows a single workbook may contain, across all sheets.
   *
   * Conversion runs inside one form submission, so this keeps a hostile or
   * accidental huge workbook from tying up a PHP worker.
   */
  public const MAX_ROWS = 50000;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileSystemInterface $fileSystem,
  ) {}

  /**
   * Whether a file is a workbook this converter handles.
   */
  public function isSpreadsheet(FileInterface $file): bool {
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    return in_array($extension, self::EXTENSIONS, TRUE);
  }

  /**
   * Checks that a workbook can be read and is within the size limits.
   *
   * Writes nothing, so a failed form validation leaves no files behind.
   *
   * @throws \Drupal\mukurtu_import\SpreadsheetConversionException
   */
  public function validate(FileInterface $file): void {
    $this->readSheets($file, function (string $name, iterable $rows): void {
      // Drain the rows so the row limit is enforced.
      iterator_count($rows);
    });
  }

  /**
   * Converts a workbook into one managed CSV file per sheet.
   *
   * The CSV files are created in $directory with the same owner and status
   * as the workbook. The workbook itself is left in place; the caller
   * decides whether to delete it.
   *
   * @param \Drupal\file\FileInterface $file
   *   The uploaded workbook.
   * @param string $directory
   *   The stream wrapper URI of the directory to write the CSV files to.
   *
   * @return \Drupal\file\FileInterface[]
   *   The new CSV files, in sheet order.
   *
   * @throws \Drupal\mukurtu_import\SpreadsheetConversionException
   */
  public function convert(FileInterface $file, string $directory): array {
    $readError = $this->t('Could not read spreadsheet %file.', ['%file' => $file->getFilename()]);
    // Always end with one slash, so "public://" and "private://x/" both work.
    $directory = str_ends_with($directory, '/') ? $directory : "$directory/";
    $this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    $basename = pathinfo($file->getFilename(), PATHINFO_FILENAME);

    // Rows stream straight to disk, one CSV per sheet, so a large workbook
    // never has to fit in memory.
    $uris = [];
    try {
      $this->readSheets($file, function (string $name, iterable $rows) use ($directory, $basename, &$uris): void {
        $filename = $this->sanitizeFilename("$basename - $name") . '.csv';
        $uri = $this->fileSystem->getDestinationFilename($directory . $filename, FileExists::Rename);
        $uris[] = $uri;
        $writer = Writer::from($uri, 'w');
        $writer->insertAll($rows);
      });

      // A single-sheet workbook keeps its own name.
      if (count($uris) === 1) {
        $uris[0] = $this->fileSystem->move($uris[0], $directory . $this->sanitizeFilename($basename) . '.csv', FileExists::Rename);
      }
    }
    catch (\Throwable $e) {
      foreach ($uris as $uri) {
        $this->fileSystem->delete($uri);
      }
      throw $e instanceof SpreadsheetConversionException ? $e : new SpreadsheetConversionException($readError, 0, $e);
    }

    $created = [];
    foreach ($uris as $uri) {
      /** @var \Drupal\file\FileInterface $csv */
      $csv = $this->entityTypeManager->getStorage('file')->create([
        'uri' => $uri,
        'filename' => $this->fileSystem->basename($uri),
        'filemime' => 'text/csv',
        'uid' => $file->getOwnerId(),
        'status' => $file->isPermanent() ? FileInterface::STATUS_PERMANENT : 0,
      ]);
      $csv->save();
      $created[] = $csv;
    }

    return $created;
  }

  /**
   * Streams each usable sheet of a workbook to a callback.
   *
   * @param \Drupal\file\FileInterface $file
   *   The workbook.
   * @param callable $onSheet
   *   Called with the sheet name and an iterable of its rows (header first),
   *   each an array of strings the same width as the header. Sheets with no
   *   header row are skipped. The callback must consume the rows before
   *   returning.
   *
   * @throws \Drupal\mukurtu_import\SpreadsheetConversionException
   */
  protected function readSheets(FileInterface $file, callable $onSheet): void {
    $readError = $this->t('Could not read spreadsheet %file.', ['%file' => $file->getFilename()]);
    $limitError = $this->t('Spreadsheet %file has too many sheets or rows to import.', ['%file' => $file->getFilename()]);

    // Workbooks are zip files, which need a local path to open. Files may
    // live on a remote stream wrapper, so always read from a local copy.
    $path = tempnam(sys_get_temp_dir(), 'mukurtu_import_');
    if ($path === FALSE || !@copy($file->getFileUri(), $path)) {
      if ($path !== FALSE) {
        @unlink($path);
      }
      throw new SpreadsheetConversionException($readError);
    }

    $reader = $this->createReader($file);
    $sheetCount = 0;
    $rowCount = 0;
    $usableSheets = 0;

    try {
      $reader->open($path);
      foreach ($reader->getSheetIterator() as $sheet) {
        if (++$sheetCount > self::MAX_SHEETS) {
          throw new SpreadsheetConversionException($limitError);
        }
        if ($sheet instanceof SheetWithVisibilityInterface && !$sheet->isVisible()) {
          continue;
        }

        $rows = $this->readSheetRows($sheet, $rowCount, $limitError);
        // Starting the generator reads up to the header row; a sheet without
        // one has nothing to import.
        if (!$rows->valid()) {
          continue;
        }
        $usableSheets++;
        $onSheet($sheet->getName(), $rows);
      }
    }
    catch (SpreadsheetConversionException $e) {
      throw $e;
    }
    catch (\Throwable $e) {
      throw new SpreadsheetConversionException($readError, 0, $e);
    }
    finally {
      $reader->close();
      @unlink($path);
    }

    if ($usableSheets === 0) {
      throw new SpreadsheetConversionException($readError);
    }
  }

  /**
   * Yields one sheet's rows as strings, header first.
   *
   * @param \OpenSpout\Reader\SheetInterface $sheet
   *   The sheet.
   * @param int $rowCount
   *   Running count of data rows across the workbook.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $limitError
   *   The message to throw when the row limit is passed.
   *
   * @return \Generator
   *   Rows as arrays of strings. Yields nothing if the sheet has no header.
   */
  protected function readSheetRows(SheetInterface $sheet, int &$rowCount, TranslatableMarkup $limitError): \Generator {
    $width = NULL;
    foreach ($sheet->getRowIterator() as $row) {
      $values = $this->rowToStrings($row);

      if ($width === NULL) {
        // The first non-empty row is the header. Trailing blank header cells
        // are columns nobody named, so they set nothing.
        while ($values !== [] && trim((string) end($values)) === '') {
          array_pop($values);
        }
        if ($values === []) {
          continue;
        }
        $width = count($values);
        yield $values;
        continue;
      }

      $values = array_slice(array_pad($values, $width, ''), 0, $width);
      if (implode('', $values) === '') {
        continue;
      }
      if (++$rowCount > self::MAX_ROWS) {
        throw new SpreadsheetConversionException($limitError);
      }
      yield $values;
    }
  }

  /**
   * Converts a row's cells to the strings a CSV would hold.
   *
   * @return string[]
   *   The cell values.
   */
  protected function rowToStrings(Row $row): array {
    return array_map(fn (Cell $cell): string => $this->cellToString($cell), $row->getCells());
  }

  /**
   * Converts one cell to a string.
   *
   * Numbers that are whole stay whole (no "12.0"), dates use ISO 8601 so the
   * importer can parse them, and formula cells use their last computed value.
   */
  protected function cellToString(Cell $cell): string {
    if ($cell instanceof ErrorCell) {
      return '';
    }
    $value = $cell instanceof FormulaCell ? $cell->getComputedValue() : $cell->getValue();
    return $this->valueToString($value);
  }

  /**
   * Converts a typed cell value to a string.
   */
  protected function valueToString(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    if (is_int($value)) {
      return (string) $value;
    }
    if (is_float($value)) {
      // Whole floats beyond the int range would otherwise print as "1.0E+20".
      if (floor($value) === $value && abs($value) < 1e15) {
        return number_format($value, 0, '.', '');
      }
      return (string) $value;
    }
    if ($value instanceof \DateTimeInterface) {
      $time = $value->format('H:i:s');
      // Excel stores a time with no date as a fraction of a day counted from
      // its 1899 base date.
      if ((int) $value->format('Y') < 1900) {
        return $time;
      }
      return $time === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d\TH:i:s');
    }
    if ($value instanceof \DateInterval) {
      $hours = ($value->days !== FALSE ? $value->days * 24 : 0) + $value->h;
      return ($value->invert ? '-' : '') . sprintf('%d:%02d:%02d', $hours, $value->i, $value->s);
    }
    return (string) $value;
  }

  /**
   * Creates a reader for the workbook's format.
   */
  protected function createReader(FileInterface $file): ReaderInterface {
    $extension = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
    return $extension === 'ods' ? new OdsReader() : new XlsxReader();
  }

  /**
   * Replaces characters that are unsafe in file names.
   */
  protected function sanitizeFilename(string $name): string {
    $name = preg_replace('/[\x00-\x1F\/\\\\:*?"<>|]+/u', '_', $name) ?? '';
    $name = trim($name, " .\t");
    return $name === '' ? 'sheet' : $name;
  }

}
