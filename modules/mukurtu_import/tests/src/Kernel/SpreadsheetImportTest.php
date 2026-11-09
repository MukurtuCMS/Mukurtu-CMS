<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\File\FileExists;
use Drupal\Core\Form\FormState;
use Drupal\file\Entity\File;
use Drupal\file\FileInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\mukurtu_import\Form\ImportFileUploadForm;
use Drupal\mukurtu_import\SpreadsheetConversionException;
use Drupal\mukurtu_import\SpreadsheetConverter;
use Drupal\node\Entity\Node;
use League\Csv\Bom;
use League\Csv\Reader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests importing XLSX and ODS workbooks by converting them to CSV.
 */
#[CoversClass(SpreadsheetConverter::class)]
#[CoversClass(ImportFileUploadForm::class)]
#[Group('mukurtu_import')]
class SpreadsheetImportTest extends MukurtuImportTestBase {

  /**
   * The converter under test.
   */
  protected SpreadsheetConverter $converter;

  /**
   * {@inheritdoc}
   */
  protected function setUpFilesystem(): void {
    parent::setUpFilesystem();
    // The upload form keeps metadata files under private://.
    $this->setSetting('file_private_path', $this->siteDirectory . '/private');
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->converter = $this->container->get('mukurtu_import.spreadsheet_converter');
  }

  /**
   * Workbooks saved by LibreOffice, in both formats.
   */
  public static function fixtureProvider(): array {
    return [
      'xlsx' => ['workbook.xlsx'],
      'ods' => ['workbook.ods'],
    ];
  }

  /**
   * Cell values come through as the text a person would expect.
   *
   * The fixtures are generated from tests/fixtures/workbook.fods with
   * LibreOffice, so they carry real number, date, and boolean formats.
   */
  #[DataProvider('fixtureProvider')]
  public function testConvertsCellValuesToText(string $fixture): void {
    $files = $this->converter->convert($this->createFixtureFile($fixture), 'public://converted');

    // The hidden sheet and the empty sheet are skipped. The header-only sheet
    // is kept, the same as an uploaded header-only CSV.
    $this->assertSame(['workbook - Items.csv', 'workbook - Header only.csv'], array_map(fn (FileInterface $file) => $file->getFilename(), $files));

    $this->assertSame([
      ['id', 'title', 'count', 'ratio', 'date', 'datetime', 'flag', 'sum'],
      // Leading zeros survive, whole numbers have no ".0", dates are ISO
      // 8601, booleans are 1/0, and a formula gives its computed value.
      ['00123', 'Māori тест 日本', '42', '3.5', '2024-03-14', '2024-03-14T09:30:00', '1', '43'],
      // Short rows are padded to the header's width, and a value with
      // commas, quotes, and a line break survives the CSV round trip.
      ['7', "Comma, \"quote\"\nsecond line", '', '', '', '', '', ''],
    ], $this->readCsv($files[0]));
    $this->assertSame([['name', 'notes']], $this->readCsv($files[1]));

    $this->assertSame('text/csv', $files[0]->getMimeType());
    $this->assertTrue($files[0]->isPermanent(), 'The CSV keeps the status of the workbook it came from.');
  }

  /**
   * A workbook with one usable sheet keeps its own file name.
   */
  public function testSingleSheetKeepsWorkbookName(): void {
    $workbook = $this->createXlsx('one-sheet', ['Only' => [['title'], ['A']]]);
    $files = $this->converter->convert($workbook, 'public://converted');
    $this->assertCount(1, $files);
    $this->assertSame('one-sheet.csv', $files[0]->getFilename());
  }

  /**
   * A file that isn't really a workbook is reported, not imported.
   */
  public function testUnreadableWorkbookIsRejected(): void {
    $uri = 'public://broken.xlsx';
    file_put_contents($uri, "title\nnot a zip file\n");
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();

    try {
      $this->converter->validate($file);
      $this->fail('An unreadable workbook should be rejected.');
    }
    catch (SpreadsheetConversionException $e) {
      $this->assertSame('Could not read spreadsheet %file.', $e->getUserMessage()->getUntranslatedString());
    }
  }

  /**
   * A workbook with no header row anywhere has nothing to import.
   */
  public function testWorkbookWithNoHeaderIsRejected(): void {
    $workbook = $this->createXlsx('empty', ['Sheet1' => [['', '']]]);
    $this->expectException(SpreadsheetConversionException::class);
    $this->converter->validate($workbook);
  }

  /**
   * Workbooks over the row limit are refused before anything is written.
   */
  public function testRowLimit(): void {
    $rows = [['title']];
    for ($i = 0; $i <= SpreadsheetConverter::MAX_ROWS; $i++) {
      $rows[] = ["Row $i"];
    }
    $workbook = $this->createXlsx('big', ['Big' => $rows]);

    try {
      $this->converter->validate($workbook);
      $this->fail('A workbook over the row limit should be rejected.');
    }
    catch (SpreadsheetConversionException $e) {
      $this->assertSame('Spreadsheet %file has too many sheets or rows to import.', $e->getUserMessage()->getUntranslatedString());
    }
  }

  /**
   * Workbooks over the sheet limit are refused.
   */
  public function testSheetLimit(): void {
    $sheets = [];
    for ($i = 0; $i <= SpreadsheetConverter::MAX_SHEETS; $i++) {
      $sheets["Sheet $i"] = [['title'], ['x']];
    }
    $workbook = $this->createXlsx('wide', $sheets);

    $this->expectException(SpreadsheetConversionException::class);
    $this->converter->validate($workbook);
  }

  /**
   * A converted workbook imports exactly like a CSV.
   */
  public function testConvertedWorkbookImports(): void {
    $node = Node::create([
      'title' => 'Before',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $workbook = $this->createXlsx('update', ['Content' => [['nid', 'title'], [(int) $node->id(), 'Æ after']]]);
    $files = $this->converter->convert($workbook, 'public://converted');

    $result = $this->importCsvFile($files[0], [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ]);
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertSame('Æ after', Node::load($node->id())->getTitle());
  }

  /**
   * A CSV that starts with a byte order mark imports cleanly.
   *
   * CSV exports start with a UTF-8 byte order mark so Excel reads them. The
   * mark must not end up in the first column name on the way back in.
   */
  public function testCsvWithByteOrderMarkImports(): void {
    $node = Node::create([
      'title' => 'Before',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();

    $uri = 'public://bom.csv';
    file_put_contents($uri, Bom::Utf8->value . "nid,title\n{$node->id()},Ō after\n");
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();

    $result = $this->importCsvFile($file, [
      ['target' => 'nid', 'source' => 'nid'],
      ['target' => 'title', 'source' => 'title'],
    ]);
    $this->assertSame(MigrationInterface::RESULT_COMPLETED, $result);
    $this->assertSame('Ō after', Node::load($node->id())->getTitle());
  }

  /**
   * Submitting the upload form swaps each workbook for its CSV files.
   */
  public function testUploadFormReplacesWorkbookWithCsvFiles(): void {
    $form_object = ImportFileUploadForm::create($this->container);
    $directory = $form_object->getMetadataUploadLocation();
    $workbook = $this->createXlsx('upload', [
      'People' => [['name'], ['Ana']],
      'Places' => [['name'], ['Here']],
    ], $directory);
    $csv = $this->createCsvFile([['title'], ['plain']]);
    $csv_uri = $this->container->get('file_system')->copy($csv->getFileUri(), $directory . 'plain.csv');
    $csv->setFileUri($csv_uri);
    $csv->setFilename('plain.csv');
    $csv->save();

    $form_state = new FormState();
    $form_state->setValue('metadata_files', [$workbook->id(), $csv->id()]);
    $form = $this->buildUploadForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);
    $this->assertSame([], $form_state->getErrors());
    $form_object->submitForm($form, $form_state);

    $this->assertNull(File::load($workbook->id()), 'The workbook is deleted once converted.');
    $names = array_map(fn (FileInterface $file) => $file->getFilename(), File::loadMultiple($form_object->getMetadataFiles()));
    sort($names);
    $this->assertSame(['plain.csv', 'upload - People.csv', 'upload - Places.csv'], $names);
    $this->assertCount(3, $form_object->getMetadataFileWeights());
  }

  /**
   * A bad workbook fails validation and nothing is converted.
   */
  public function testUploadFormReportsBadWorkbook(): void {
    $form_object = ImportFileUploadForm::create($this->container);
    $directory = $form_object->getMetadataUploadLocation();
    $this->container->get('file_system')->prepareDirectory($directory, 1);
    file_put_contents($directory . 'broken.ods', 'not a workbook');
    $file = File::create(['uri' => $directory . 'broken.ods', 'status' => 1]);
    $file->save();

    $form_state = new FormState();
    $form_state->setValue('metadata_files', [$file->id()]);
    $form = $this->buildUploadForm($form_object, $form_state);
    $form_object->validateForm($form, $form_state);

    $errors = $form_state->getErrors();
    $this->assertCount(1, $errors);
    $this->assertStringContainsString('broken.ods', (string) reset($errors));
    $this->assertSame([(int) $file->id()], array_map('intval', array_values($form_object->getMetadataFiles())));
  }

  /**
   * Builds the upload form as if its Next button was pressed.
   *
   * Only the parts validateForm() and submitForm() read are filled in; the
   * full form builder would try to process real file uploads.
   */
  protected function buildUploadForm(ImportFileUploadForm $form_object, FormState $form_state): array {
    $form = $form_object->buildForm([], $form_state);
    $form['metadata_files']['#parents'] = ['metadata_files'];
    $form['actions']['submit']['#parents'] = ['submit'];
    $form_state->setTriggeringElement($form['actions']['submit']);
    return $form;
  }

  /**
   * Copies a fixture workbook into public:// as a managed file.
   */
  protected function createFixtureFile(string $name): FileInterface {
    $uri = $this->container->get('file_system')->copy(__DIR__ . '/../../fixtures/' . $name, 'public://' . $name, FileExists::Replace);
    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    return $file;
  }

  /**
   * Writes an XLSX workbook and returns it as a managed file.
   *
   * @param string $basename
   *   The file name without extension.
   * @param array $sheets
   *   Rows keyed by sheet name.
   * @param string $directory
   *   The directory URI to write to.
   */
  protected function createXlsx(string $basename, array $sheets, string $directory = 'public://'): FileInterface {
    $file_system = $this->container->get('file_system');
    $file_system->prepareDirectory($directory, 1);
    $uri = (str_ends_with($directory, '/') ? $directory : "$directory/") . "$basename.xlsx";

    // OpenSpout needs a local path; public:// is a virtual file system here.
    $path = tempnam(sys_get_temp_dir(), 'xlsx_test_');
    $writer = new XlsxWriter();
    $writer->openToFile($path);
    $first = TRUE;
    foreach ($sheets as $name => $rows) {
      if (!$first) {
        $writer->addNewSheetAndMakeItCurrent();
      }
      $first = FALSE;
      $writer->getCurrentSheet()->setName($name);
      foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
      }
    }
    $writer->close();
    $file_system->move($path, $uri, FileExists::Replace);

    $file = File::create(['uri' => $uri, 'status' => 1]);
    $file->save();
    return $file;
  }

  /**
   * Reads every record of a CSV file.
   */
  protected function readCsv(FileInterface $file): array {
    return iterator_to_array(Reader::from($file->getFileUri(), 'r')->getRecords(), FALSE);
  }

}
