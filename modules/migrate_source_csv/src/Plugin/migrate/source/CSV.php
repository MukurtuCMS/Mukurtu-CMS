<?php

namespace Drupal\migrate_source_csv\Plugin\migrate\source;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Utility\NestedArray;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use League\Csv\Reader;

/**
 * Source for CSV files.
 *
 * Available configuration options:
 * - path: Path to the  CSV file. File streams are supported. Use /dev/null to
 *   get an empty source (useful for migration_lookup).
 * - ids: Array of column names that uniquely identify each record.
 * - header_offset: (optional) The record to be used as the CSV header and the
 *   thereby each record's field name. Defaults to 0 and because records are
 *   zero indexed. Can be set to null to indicate no header record.
 * - fields: (optional) nested array of names and labels to use instead of a
 *   header record. Will overwrite values provided by header record. If used,
 *   name is required. If no label is provided, name is used instead for the
 *   field description.
 * - delimiter: (optional) The field delimiter (one character only). Defaults to
 *   a comma (,).
 * - enclosure: (optional) The field enclosure character (one character only).
 *   Defaults to double quote marks.
 * - escape: (optional) The field escape character (one character only).
 *   Defaults to a backslash (\).
 * - create_record_number: (optional) Boolean value specifying whether to create
 *   an incremented value for each record in the file. Defaults to FALSE.
 * - record_number_field: (optional) The name of a field that holds an
 *   incremented value for each record in the file. Defaults to record_number.
 *
 * @codingStandardsIgnoreStart
 *
 * Example with minimal options:
 * @code
 * source:
 *   plugin: csv
 *   path: /tmp/countries.csv
 *   ids: [id]
 *
 * # countries.csv
 * id,country
 * 1,Nicaragua
 * 2,Spain
 * 3,United States
 * @endcode
 *
 * In this example above, the migration source will use a single-column id using the
 * value from the 'id' column of the CSV file.
 *
 * Example with most options configured:
 * @code
 * source:
 *   plugin: csv
 *   path: /tmp/countries.csv
 *   ids: [id]
 *   delimiter: '|'
 *   enclosure: "'"
 *   escape: '`'
 *   header_offset: null
 *   fields:
 *     -
 *       name: id
 *       label: ID
 *     -
 *       name: country
 *       label: Country
 *
 * # countries.csv
 * 'really long string that makes this unique'|'United States'
 * 'even longer really long string that makes this unique'|'Nicaragua'
 * 'even more longer really long string that makes this unique'|'Spain'
 * 'escaped data'|'one`'s country'
 * @endcode
 *
 * In this example above, we override the default character controls for delimiter,
 * enclosure and escape. We also set a null header offset to indicate no header.
 *
 * @codingStandardsIgnoreEnd
 *
 * @see http://php.net/manual/en/splfileobject.setcsvcontrol.php
 *
 * @MigrateSource(
 *   id = "csv",
 *   source_module = "migrate_source_csv"
 * )
 */
class CSV extends SourcePluginBase implements ConfigurableInterface {

  /**
   * {@inheritdoc}
   *
   * @throws \InvalidArgumentException
   * @throws \Drupal\migrate\MigrateException
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->setConfiguration($configuration);

    // Replace /dev/null with an empty regular file.
    if ($this->configuration['path'] === '/dev/null') {
      $this->configuration['path'] = __DIR__ . '/empty.csv';
      $this->configuration['header_offset'] = NULL;
    }

    // Path is required.
    if (empty($this->configuration['path'])) {
      throw new \InvalidArgumentException('You must declare the "path" to the source CSV file in your source settings.');
    }
    // IDs are required.
    if (empty($this->configuration['ids']) || !is_array($this->configuration['ids'])) {
      throw new \InvalidArgumentException('You must declare "ids" as a unique array of fields in your source settings.');
    }
    // IDs must be an array of strings.
    if ($this->configuration['ids'] !== array_unique(array_filter($this->configuration['ids'], 'is_string'))) {
      throw new \InvalidArgumentException('The ids must a flat array with unique string values.');
    }
    // CSV character control characters must be exactly 1 character.
    foreach (['delimiter', 'enclosure', 'escape'] as $character) {
      if (1 !== strlen($this->configuration[$character])) {
        throw new \InvalidArgumentException(sprintf('%s must be a single character; %s given', $character, $this->configuration[$character]));
      }
    }
    // The configuration "header_offset" must be null or an integer.
    if (!(NULL === $this->configuration['header_offset'] || is_int($this->configuration['header_offset']))) {
      throw new \InvalidArgumentException('The configuration "header_offset" must be null or an integer.');
    }
    // The configuration "header_offset" must be greater or equal to 0.
    if (NULL !== $this->configuration['header_offset'] && 0 > $this->configuration['header_offset']) {
      throw new \InvalidArgumentException('The configuration "header_offset" must be greater or equal to 0.');
    }
    // If set, all fields must have a least a defined "name" property.
    if ($this->configuration['fields']) {
      foreach ($this->configuration['fields'] as $delta => $field) {
        if (!isset($field['name'])) {
          throw new \InvalidArgumentException(sprintf('The "name" configuration for "fields" in index position %s is not defined.', $delta));
        }
      }
    }
    // If "create_record_number" is specified, "record_number_field" must be a
    // non-empty string.
    if ($this->configuration['create_record_number'] && (!is_scalar($this->configuration['record_number_field']) || (empty($this->configuration['record_number_field'])))) {
      throw new \InvalidArgumentException('The configuration "record_number_field" must be a non-empty string.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'path' => '',
      'ids' => [],
      'header_offset' => 0,
      'fields' => [],
      'delimiter' => ",",
      'enclosure' => "\"",
      'escape' => "\\",
      'create_record_number' => FALSE,
      'record_number_field' => 'record_number',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    // We must preserve integer keys for column_name mapping.
    $this->configuration = NestedArray::mergeDeepArray([$this->defaultConfiguration(), $configuration], TRUE);
  }

  /**
   * Return a string representing the source file path.
   *
   * @return string
   *   The file path.
   */
  public function __toString() {
    return $this->configuration['path'];
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \League\Csv\Exception
   */
  public function initializeIterator() {
    $header = $this->getReader()->getHeader();
    if ($this->configuration['fields']) {
      // If there is no header record, we need to flip description and name so
      // the name becomes the header record. League/csv requires the header keys
      // to be integers >= 0.
      $header = array_keys($this->fields());
    }
    return $this->getGenerator($this->getReader()->getRecords($header));
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = [];
    foreach ($this->configuration['ids'] as $value) {
      $ids[$value]['type'] = 'string';
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields() {
    // If fields are not defined, use the header record.
    if (empty($this->configuration['fields'])) {
      $header = $this->getReader()->getHeader();
      return array_combine($header, $header);
    }
    $fields = [];
    foreach ($this->configuration['fields'] as $field) {
      $fields[$field['name']] = $field['label'] ?? $field['name'];
    }
    return $fields;
  }

  /**
   * Get the generator.
   *
   * @param \Iterator $records
   *   The CSV records.
   *
   * @codingStandardsIgnoreStart
   *
   * @return \Generator
   *   The records generator.
   *
   * @codingStandardsIgnoreEnd
   */
  protected function getGenerator(\Iterator $records) {
    $record_num = $this->configuration['header_offset'] ?? 0;
    foreach ($records as $record) {
      if ($this->configuration['create_record_number']) {
        $record[$this->configuration['record_number_field']] = ++$record_num;
      }
      yield $record;
    }
  }

  /**
   * Get the CSV reader.
   *
   * @return \League\Csv\Reader
   *   The reader.
   *
   * @throws \Drupal\migrate\MigrateException
   * @throws \League\Csv\Exception
   */
  protected function getReader() {
    $reader = $this->createReader();
    $reader->setDelimiter($this->configuration['delimiter']);
    $reader->setEnclosure($this->configuration['enclosure']);
    $reader->setEscape($this->configuration['escape']);
    $reader->setHeaderOffset($this->configuration['header_offset']);
    return $reader;
  }

  /**
   * Construct a new CSV reader.
   *
   * @return \League\Csv\Reader
   *   The reader.
   */
  protected function createReader() {
    if (!file_exists($this->configuration['path'])) {
      throw new \RuntimeException(sprintf('File "%s" was not found.', $this->configuration['path']));
    }
    $csv = fopen($this->configuration['path'], 'r');
    if (!$csv) {
      throw new \RuntimeException(sprintf('File "%s" could not be opened.', $this->configuration['path']));
    }
    return Reader::createFromStream($csv);
  }

}
