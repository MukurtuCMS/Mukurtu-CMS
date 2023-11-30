<?php

namespace Drupal\Tests\original_date\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use InvalidArgumentException;

class OriginalDateTest extends KernelTestBase {
  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'user', 'system', 'field', 'original_date'];

  protected $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installSchema('system', 'sequences');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig('field');

    NodeType::create(['type' => 'article'])->save();

    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'type' => 'original_date',
      'cardinality' => -1,
    ]);
    $field_storage->save();

    FieldConfig::create([
      'field_name' => 'field_original_date',
      'entity_type' => 'node',
      'bundle' => 'article',
      'label' => 'Original Date',
    ])->save();

    $node = Node::create([
      'title' => 'Title',
      'type' => 'article',
      'status' => TRUE,
      'uid' => 1,
    ]);
    $node->save();
    $this->node = $node;
  }

  /**
   * Test setting empty original date.
   */
  public function testEmptyOriginalDate() {
    $this->node->set('field_original_date', '');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('', $originalDate[0]['date']);
    $this->assertEquals('', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting null original date. This unsets the original date field.
   */
  public function testNullOriginalDate() {
    $this->node->set('field_original_date', NULL);

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEmpty($originalDate);
  }

  /**
   * Test setting valid YYYY original date.
   */
  public function testValidYear() {
    $this->node->set('field_original_date', '1999');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('1999', $originalDate[0]['date']);
    $this->assertEquals('1999', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting valid YYYY-MM original date.
   */
  public function testValidYearMonthDashDelimited()
  {
    $this->node->set('field_original_date', '1999-8');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('1999-08', $originalDate[0]['date']);
    $this->assertEquals('1999', $originalDate[0]['year']);
    $this->assertEquals('8', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting valid YYYY-MM-DD original date.
   */
  public function testValidYearMonthDayDashDelimited()
  {
    $this->node->set('field_original_date', '1999-8-12');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('1999-08-12', $originalDate[0]['date']);
    $this->assertEquals('1999', $originalDate[0]['year']);
    $this->assertEquals('8', $originalDate[0]['month']);
    $this->assertEquals('12', $originalDate[0]['day']);
  }

  /**
   * Test setting valid YYYY/MM original date.
   */
  public function testValidYearMonthForwardSlashDelimited()
  {
    $this->node->set('field_original_date', '1999/8');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('1999-08', $originalDate[0]['date']);
    $this->assertEquals('1999', $originalDate[0]['year']);
    $this->assertEquals('8', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting valid YYYY/MM/DD original date.
   */
  public function testValidYearMonthDayForwardSlashDelimited()
  {
    $this->node->set('field_original_date', '1999/8/12');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('1999-08-12', $originalDate[0]['date']);
    $this->assertEquals('1999', $originalDate[0]['year']);
    $this->assertEquals('8', $originalDate[0]['month']);
    $this->assertEquals('12', $originalDate[0]['day']);
  }

  /**
   * Test setting invalid year.
   */
  public function testInvalidYear()
  {
    $this->expectExceptionMessage("Invalid year '0'.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '0');
  }

  /**
   * Test setting trailing zero year.
   */
  public function testTrailingZeroYear()
  {
    $this->node->set('field_original_date', '099');
    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('99', $originalDate[0]['date']);
    $this->assertEquals('99', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting invalid month.
   */
  public function testInvalidMonth()
  {
    $this->expectExceptionMessage("Invalid month '13'.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '1999-13');
  }

  /**
   * Test setting invalid day.
   */
  public function testInvalidDay()
  {
    $this->expectExceptionMessage("Invalid day '60'.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '1999-12-60');
  }

  /**
   * Test setting valid YYY original date.
   */
  public function testValidThreeDigitYear()
  {
    $this->node->set('field_original_date', '999');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('999', $originalDate[0]['date']);
    $this->assertEquals('999', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting valid YY original date.
   */
  public function testValidTwoDigitYear()
  {
    $this->node->set('field_original_date', '99');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('99', $originalDate[0]['date']);
    $this->assertEquals('99', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting valid Y original date.
   */
  public function testValidSingleDigitYear()
  {
    $this->node->set('field_original_date', '9');

    $originalDate = $this->node->get('field_original_date')->getValue();
    $this->assertEquals('9', $originalDate[0]['date']);
    $this->assertEquals('9', $originalDate[0]['year']);
    $this->assertEquals('', $originalDate[0]['month']);
    $this->assertEquals('', $originalDate[0]['day']);
  }

  /**
   * Test setting invalid date YYYY--DD (missing month).
   */
  public function testInvalidYearDay()
  {
    $this->expectExceptionMessage("Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '1999--8');
  }

  /**
   * Test setting invalid date -MM-DD (missing year).
   */
  public function testInvalidMonthDay()
  {
    $this->expectExceptionMessage("Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '-8-9');
  }

  /**
   * Test setting invalid date -MM- (month only).
   */
  public function testInvalidMonthOnly()
  {
    $this->expectExceptionMessage("Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '-8-');
  }

  /**
   * Test setting invalid date --DD (day only).
   */
  public function testInvalidDayOnly()
  {
    $this->expectExceptionMessage("Dates must be in YYYY, YYYY-MM, or YYYY-MM-DD format.");
    $this->expectException(InvalidArgumentException::class);
    $this->node->set('field_original_date', '--8');
  }
}
