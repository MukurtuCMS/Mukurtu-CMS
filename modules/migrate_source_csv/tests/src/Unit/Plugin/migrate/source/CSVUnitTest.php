<?php

namespace Drupal\Tests\migrate_source_csv\Unit\Plugin\migrate\source;

use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_source_csv\Plugin\migrate\source\CSV;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @coversDefaultClass \Drupal\migrate_source_csv\Plugin\migrate\source\CSV
 *
 * @group migrate_source_csv
 */
class CSVUnitTest extends UnitTestCase {

  /**
   * The plugin id.
   *
   * @var string
   */
  protected $pluginId;

  /**
   * The plugin definition.
   *
   * @var array
   */
  protected $pluginDefinition;

  /**
   * The migration plugin.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * A file path with standard character controls.
   *
   * @var string
   */
  protected $standardCharsPath;

  /**
   * A file path with non-standard character controls.
   *
   * @var string
   */
  protected $nonStandardCharsPath;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->pluginId = 'test csv migration';
    $this->pluginDefinition = [];
    $migration = $this->prophesize(MigrationInterface::class);
    $migration->getIdMap()
      ->willReturn(NULL);
    $this->migration = $migration->reveal();

    $standard_chars = <<<'EOD'
id,first_name,last_name,email,country,ip_address
1,Justin,Dean,jdean0@example.com,Indonesia,60.242.130.40
2,Joan,Jordan,jjordan1@example.com,Thailand,137.230.209.171
EOD;
    $non_standard_chars = <<<'EOD'
1|%Justin%|Dean|jdean0@example.com|Indonesia|60.242.130.40
2|Joan|Jordan|jjordan1@example.com|Thailand|137.230.209.171

EOD;

    $root_dir = vfsStream::setup('root');
    $this->standardCharsPath = vfsStream::newFile('data.csv')
      ->at($root_dir)
      ->withContent($standard_chars)
      ->url();
    $this->nonStandardCharsPath = vfsStream::newFile('data_edge_case.csv')
      ->at($root_dir)
      ->withContent($non_standard_chars)
      ->url();
  }

  /**
   * Tests the construction of CSV.
   *
   * @covers ::__construct
   */
  public function testCreate(): void {
    $configuration = [
      'path' => $this->standardCharsPath,
      'ids' => ['id'],
    ];
    $csv = new CSV($configuration, $this->pluginId, $this->pluginDefinition, $this->migration);
    $this->assertInstanceOf(CSV::class, $csv);
  }

  /**
   * Tests that a missing path will throw an exception.
   */
  public function testMigrateExceptionPathMissing(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('You must declare the "path" to the source CSV file in your source settings.');
    new CSV([], $this->pluginId, $this->pluginDefinition, $this->migration);
  }

  /**
   * Tests that missing ids will throw an exception.
   */
  public function testMigrateExceptionKeysMissing(): void {
    $configuration = [
      'path' => $this->standardCharsPath,
    ];
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('You must declare "ids" as a unique array of fields in your source settings.');
    new CSV($configuration, $this->pluginId, $this->pluginDefinition, $this->migration);
  }

  /**
   * Tests that toString functions as expected.
   *
   * @covers ::__toString
   */
  public function testToString(): void {
    $configuration = [
      'path' => $this->standardCharsPath,
      'ids' => ['id'],
    ];
    $csv = new CSV($configuration, $this->pluginId, $this->pluginDefinition, $this->migration);
    $this->assertEquals($configuration['path'], (string) $csv);
  }

  /**
   * Tests initialization of the iterator.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param array $expected
   *   The expected results.
   *
   * @covers ::initializeIterator
   * @dataProvider iteratorDataProvider
   */
  public function testInitializeIterator(array $configuration, array $expected): void {
    $file_path = $this->standardCharsPath;
    if (isset($configuration['path']) && $configuration['path'] === 'non standard') {
      $file_path = $this->nonStandardCharsPath;
    }
    // Set the file path here so the virtual stream can be created in setUp.
    $configuration['path'] = $file_path;

    $csv = new CSV($configuration, $this->pluginId, $this->pluginDefinition, $this->migration);
    $iterator = $csv->initializeIterator();
    $this->assertEquals(count($expected), iterator_count($iterator));
    $iterator = $csv->initializeIterator();
    foreach ($expected as $record) {
      $this->assertSame($record, $iterator->current());
      $iterator->next();
    }

  }

  /**
   * Data provider for iterator testing.
   *
   * @return array
   *   The test case.
   */
  public static function iteratorDataProvider(): array {
    $data['non standard'] = [
      'configuration' => [
        'ids' => ['ids'],
        'path' => 'non standard',
        'header_offset' => NULL,
        'delimiter' => '|',
        'enclosure' => '%',
        'escape' => '`',
      ],
      'expected rows' => [
        [
          '1',
          'Justin',
          'Dean',
          'jdean0@example.com',
          'Indonesia',
          '60.242.130.40',
        ],
        [
          '2',
          'Joan',
          'Jordan',
          'jjordan1@example.com',
          'Thailand',
          '137.230.209.171',
        ],
      ],
    ];
    $data['standard'] = [
      'configuration' => [
        'ids' => ['ids'],
      ],
      'expected rows' => [
        [
          'id' => '1',
          'first_name' => 'Justin',
          'last_name' => 'Dean',
          'email' => 'jdean0@example.com',
          'country' => 'Indonesia',
          'ip_address' => '60.242.130.40',
        ],
        [
          'id' => '2',
          'first_name' => 'Joan',
          'last_name' => 'Jordan',
          'email' => 'jjordan1@example.com',
          'country' => 'Thailand',
          'ip_address' => '137.230.209.171',
        ],
      ],
    ];
    $data['with defined fields'] = [
      'configuration' => [
        'ids' => ['ids'],
        'fields' => [
          [
            'name' => 'id',
          ],
          [
            'name' => 'first_name',
            'label' => 'First Name',
          ],
        ],
      ],
      'expected rows' => [
        [
          'id' => '1',
          'first_name' => 'Justin',
        ],
        [
          'id' => '2',
          'first_name' => 'Joan',
        ],
      ],
    ];
    $data['default record number field name'] = [
      'configuration' => [
        'ids' => ['id'],
        'create_record_number' => TRUE,
      ],
      'expected rows' => [
        [
          'id' => '1',
          'first_name' => 'Justin',
          'last_name' => 'Dean',
          'email' => 'jdean0@example.com',
          'country' => 'Indonesia',
          'ip_address' => '60.242.130.40',
          'record_number' => 1,
        ],
        [
          'id' => '2',
          'first_name' => 'Joan',
          'last_name' => 'Jordan',
          'email' => 'jjordan1@example.com',
          'country' => 'Thailand',
          'ip_address' => '137.230.209.171',
          'record_number' => 2,
        ],
      ],
    ];
    $data['custom record number field name'] = [
      'configuration' => [
        'ids' => ['MyRowNumber'],
        'create_record_number' => TRUE,
        'record_number_field' => 'MyRowNumber',
        'header_offset' => 1,
      ],
      'expected rows' => [
        [
          '1' => 'id',
          'Justin' => 'first_name',
          'Dean' => 'last_name',
          'jdean0@example.com' => 'email',
          'Indonesia' => 'country',
          '60.242.130.40' => 'ip_address',
          'MyRowNumber' => 2,
        ],
        [
          '1' => '2',
          'Justin' => 'Joan',
          'Dean' => 'Jordan',
          'jdean0@example.com' => 'jjordan1@example.com',
          'Indonesia' => 'Thailand',
          '60.242.130.40' => '137.230.209.171',
          'MyRowNumber' => 3,
        ],
      ],
    ];
    return $data;
  }

  /**
   * Tests that ids are properly identified.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param array $expected
   *   The expected results.
   *
   * @covers ::getIds
   * @dataProvider idsDataProvider
   */
  public function testGetIds(array $configuration, array $expected): void {
    $csv = new CSV($configuration + ['path' => $this->standardCharsPath], $this->pluginId, $this->pluginDefinition, $this->migration);
    $this->assertSame($expected, $csv->getIds());
  }

  /**
   * Data provider for IDs testing.
   *
   * @return array
   *   The test case.
   */
  public static function idsDataProvider(): array {
    $data['ids'] = [
      'configuration' => [
        'ids' => [
          'id',
          'paragraph',
        ],
      ],
      'expected' => [
        'id' => [
          'type' => 'string',
        ],
        'paragraph' => [
          'type' => 'string',
        ],
      ],
    ];
    return $data;
  }

  /**
   * Tests that fields have a machine name and description.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param array $expected
   *   The expected results.
   *
   * @covers ::fields
   * @dataProvider fieldsDataProvider
   */
  public function testFields(array $configuration, array $expected): void {
    $csv = new CSV($configuration + ['path' => $this->standardCharsPath], $this->pluginId, $this->pluginDefinition, $this->migration);
    $this->assertSame($expected, $csv->fields());
  }

  /**
   * Data provider for fields testing.
   *
   * @return array
   *   The test case.
   */
  public static function fieldsDataProvider(): array {
    $data['no fields'] = [
      'configuration' => [
        'ids' => ['id'],
      ],
      'expected' => [
        'id' => 'id',
        'first_name' => 'first_name',
        'last_name' => 'last_name',
        'email' => 'email',
        'country' => 'country',
        'ip_address' => 'ip_address',
      ],
    ];
    $data['with fields override'] = [
      'configuration' => [
        'ids' => ['id'],
        'fields' => [
          [
            'name' => 'id',
          ],
          [
            'name' => 'first_name',
            'label' => 'First Name',
          ],
        ],
      ],
      'expected' => [
        'id' => 'id',
        'first_name' => 'First Name',
      ],
    ];
    return $data;
  }

  /**
   * Tests malformed CSV file path.
   *
   * @covers ::initializeIterator
   */
  public function testMalformedFilePath(): void {
    $configuration = [
      'path' => 'non-existent-path',
      'ids' => ['id'],
    ];

    $csv = new CSV($configuration, $this->pluginId, $this->pluginDefinition, $this->migration);
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('File "non-existent-path" was not found.');
    $csv->initializeIterator();
  }

}
