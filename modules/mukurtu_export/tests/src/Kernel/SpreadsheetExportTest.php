<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\mukurtu_export\BatchExportExecutable;
use Drupal\mukurtu_export\ExportItemIdentity;
use Drupal\mukurtu_export\Plugin\MukurtuExporter\CSV;
use Drupal\mukurtu_export\SpreadsheetWorkbookWriter;
use Drupal\node\Entity\Node;
use League\Csv\Bom;
use League\Csv\Reader;
use OpenSpout\Reader\ODS\Reader as OdsReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests exporting to XLSX and ODS workbooks, and the CSV byte order mark.
 */
#[CoversClass(SpreadsheetWorkbookWriter::class)]
#[CoversClass(BatchExportExecutable::class)]
#[CoversClass(CSV::class)]
#[Group('mukurtu_export')]
class SpreadsheetExportTest extends CsvExportFieldTestBase {

  /**
   * The working directory for export output.
   */
  protected string $basepath = 'public://spreadsheet-export-test';

  /**
   * The node being exported.
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->container->get('file_system')->prepareDirectory($this->basepath, FileSystemInterface::CREATE_DIRECTORY);

    $node = Node::create([
      'title' => '00123 Māori тест',
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    $this->node = $node;
  }

  /**
   * CSV exports start with a UTF-8 byte order mark so Excel reads them.
   */
  public function testCsvExportStartsWithByteOrderMark(): void {
    $context = $this->exportNode('csv');
    $uri = $context['results']['deliverables']['metadata'][0]['uri'];

    $this->assertStringStartsWith(Bom::Utf8->value, file_get_contents($uri));

    // The importer reads headers this way; the mark must not leak into the
    // first column name.
    $reader = Reader::from($uri, 'r');
    $reader->setHeaderOffset(0);
    $this->assertSame(['Title'], $reader->getHeader());
    $this->assertSame([['Title' => '00123 Māori тест']], iterator_to_array($reader->getRecords(), FALSE));
  }

  /**
   * Workbook exports convert the CSV files, so those carry no mark.
   */
  public function testWorkbookExportCsvHasNoByteOrderMark(): void {
    $context = $this->exportNode('xlsx');
    $uri = $context['results']['deliverables']['metadata'][0]['uri'];
    $this->assertStringStartsNotWith(Bom::Utf8->value, file_get_contents($uri));
  }

  /**
   * Workbook formats.
   */
  public static function formatProvider(): array {
    return [
      'xlsx' => ['xlsx'],
      'ods' => ['ods'],
    ];
  }

  /**
   * The CSV files are replaced by one workbook with every value as text.
   */
  #[DataProvider('formatProvider')]
  public function testBuildWorkbook(string $format): void {
    $context = $this->exportNode($format);
    BatchExportExecutable::buildWorkbook($context);

    $deliverables = $context['results']['deliverables']['metadata'];
    $this->assertSame([['uri' => "{$this->basepath}/export.$format", 'entryname' => "export.$format"]], $deliverables);
    $this->assertArrayNotHasKey('workbook_fallback', $context['results']);

    $sheets = $this->readWorkbook($deliverables[0]['uri'], $format);
    // "Content - Protocol Aware Content" is cut to the 31 characters a sheet
    // name may have.
    $this->assertSame(['Content - Protocol Aware Conten' => [['Title'], ['00123 Māori тест']]], $sheets);
  }

  /**
   * CSV exports are left alone.
   */
  public function testCsvFormatSkipsWorkbook(): void {
    $context = $this->exportNode('csv');
    $before = $context['results']['deliverables']['metadata'];
    BatchExportExecutable::buildWorkbook($context);
    $this->assertSame($before, $context['results']['deliverables']['metadata']);
  }

  /**
   * A value too long for an Excel cell keeps the CSV files and warns.
   */
  public function testOverlongValueFallsBackToCsv(): void {
    $csv = $this->writeLongValueCsv();
    $context = $this->workbookContext('xlsx', [$csv]);

    BatchExportExecutable::buildWorkbook($context);

    $this->assertTrue($context['results']['workbook_fallback']);
    $this->assertSame([$csv], $context['results']['deliverables']['metadata']);
    $this->assertFileDoesNotExist("{$this->basepath}/export.xlsx");

    BatchExportExecutable::batchFinishedExport(TRUE, $context['results'], []);
    $warnings = $this->container->get('messenger')->messagesByType(MessengerInterface::TYPE_WARNING);
    $this->assertCount(1, $warnings);
    $this->assertSame('Some values are too long for an Excel workbook, so this export contains CSV files instead.', (string) $warnings[0]);
  }

  /**
   * ODS has no cell length limit, so long values still make a workbook.
   */
  public function testOverlongValueFitsInOds(): void {
    $context = $this->workbookContext('ods', [$this->writeLongValueCsv()]);
    BatchExportExecutable::buildWorkbook($context);

    $this->assertArrayNotHasKey('workbook_fallback', $context['results']);
    $sheets = $this->readWorkbook("{$this->basepath}/export.ods", 'ods');
    $this->assertSame(SpreadsheetWorkbookWriter::XLSX_MAX_CELL_LENGTH + 1, mb_strlen($sheets['Long'][1][0]));
  }

  /**
   * Sheet names are made valid and unique.
   */
  public function testSheetNames(): void {
    $writer = $this->container->get('mukurtu_export.workbook_writer');
    $used = [];
    $this->assertSame('a-b-c-d', $writer->sheetName('a[b]c:d.csv', $used));
    $this->assertSame('x-y-z', $writer->sheetName("'x/y\\z'.csv", $used));
    $this->assertSame('Sheet', $writer->sheetName('.csv', $used));
    $this->assertSame('A - B', $writer->sheetName('A - B.csv', $used));
    // Names match without regard to case, as in Excel.
    $this->assertSame('a - b (2)', $writer->sheetName('a - b.csv', $used));

    $long = str_repeat('é', 40) . '.csv';
    $this->assertSame(str_repeat('é', 31), $writer->sheetName($long, $used));
    $this->assertSame(str_repeat('é', 27) . ' (2)', $writer->sheetName($long, $used));
  }

  /**
   * Unknown formats fall back to CSV.
   */
  public function testGetFormat(): void {
    $this->assertSame('csv', CSV::getFormat([]));
    $this->assertSame('ods', CSV::getFormat(['format' => 'ods']));
    $this->assertSame('csv', CSV::getFormat(['format' => 'xls']));
  }

  /**
   * Exports the test node and returns the batch context.
   */
  protected function exportNode(string $format): array {
    $key = ExportItemIdentity::encode($this->node->id(), NULL);
    $context = $this->workbookContext($format, []);
    $context['sandbox'] = [
      'config' => $this->export_config,
      'batch' => ['entity_type_id' => 'node', 'entities' => [$key]],
    ];
    $context['results'] += [
      'config_id' => $this->export_config->id(),
      'entities' => ['node' => [$key => $key]],
      'exported_entities' => [],
      'exported_entities_count' => 0,
      'shallow_entity_ids' => [],
      'headers_written' => [],
    ];
    CSV::batchExport($context);
    CSV::batchCompleted($context);
    unset($context['sandbox']);
    return $context;
  }

  /**
   * Builds the batch context buildWorkbook() reads.
   */
  protected function workbookContext(string $format, array $metadata): array {
    return [
      'results' => [
        'settings' => ['settings_id' => $this->export_config->id(), 'format' => $format],
        'csv' => ['separator' => ',', 'enclosure' => '"', 'escape' => '\\'],
        'basepath' => $this->basepath,
        'deliverables' => ['metadata' => $metadata, 'files' => []],
      ],
    ];
  }

  /**
   * Writes a CSV deliverable with one value over Excel's cell limit.
   */
  protected function writeLongValueCsv(): array {
    $uri = "{$this->basepath}/Long.csv";
    $handle = fopen($uri, 'w');
    fputcsv($handle, ['Body'], ',', '"', '\\');
    fputcsv($handle, [str_repeat('a', SpreadsheetWorkbookWriter::XLSX_MAX_CELL_LENGTH + 1)], ',', '"', '\\');
    fclose($handle);
    return ['uri' => $uri, 'entryname' => 'Long.csv'];
  }

  /**
   * Reads a workbook into rows keyed by sheet name.
   */
  protected function readWorkbook(string $uri, string $format): array {
    // OpenSpout needs a local path.
    $path = tempnam(sys_get_temp_dir(), 'workbook_test_');
    copy($uri, $path);
    $reader = $format === 'ods' ? new OdsReader() : new XlsxReader();
    $reader->open($path);
    $sheets = [];
    foreach ($reader->getSheetIterator() as $sheet) {
      foreach ($sheet->getRowIterator() as $row) {
        $sheets[$sheet->getName()][] = $row->toArray();
      }
    }
    $reader->close();
    unlink($path);
    return $sheets;
  }

}
