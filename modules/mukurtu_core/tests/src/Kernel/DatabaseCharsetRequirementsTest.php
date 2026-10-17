<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Hook\DatabaseCharsetRequirements;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the status report warning for tables that aren't using utf8mb4.
 *
 * The hook is exercised directly rather than through
 * moduleHandler()->invokeAll('runtime_requirements'), for the same reason as
 * DevelopmentModuleRequirementsTest: core's own requirements hook fatals in a
 * kernel test unless install.inc is loaded.
 *
 * @see \Drupal\mukurtu_core\Hook\DatabaseCharsetRequirements
 */
#[Group('mukurtu_core')]
class DatabaseCharsetRequirementsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
  ];

  protected const KEY = 'mukurtu_core_database_charset';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    if (\Drupal::database()->databaseType() !== 'mysql') {
      $this->markTestSkipped('Only MySQL and MariaDB tables have a character set to check.');
    }
    $this->installSchema('user', ['users_data']);
  }

  /**
   * Runs the hook against the test database.
   */
  protected function requirements(): array {
    $hook = new DatabaseCharsetRequirements(\Drupal::database());

    return $hook->runtimeRequirements();
  }

  /**
   * Returns a table's full name, including the test's table prefix.
   */
  protected function fullTableName(string $table): string {
    return \Drupal::database()->getPrefix() . $table;
  }

  /**
   * Tables created by Drupal pass, including core's ascii columns.
   */
  public function testOkWhenEveryTableUsesUtf8mb4(): void {
    // The cache tables' cid columns are ascii, so this also shows ascii is not
    // reported.
    \Drupal::cache()->set('mukurtu_charset_test', TRUE);

    $requirements = $this->requirements();

    $this->assertSame(RequirementSeverity::OK, $requirements[static::KEY]['severity']);
    $this->assertSame('All tables use utf8mb4', (string) $requirements[static::KEY]['value']);
  }

  /**
   * A table converted to utf8mb3 is reported by name.
   */
  public function testWarningNamesUtf8mb3Table(): void {
    \Drupal::database()->query("CREATE TABLE {mukurtu_charset_test} ([value] VARCHAR(255) NOT NULL) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");

    $requirement = $this->requirements()[static::KEY];

    $this->assertSame(RequirementSeverity::Warning, $requirement['severity']);
    $this->assertSame("1 table doesn't use utf8mb4", (string) $requirement['value']);
    $this->assertStringContainsString($this->fullTableName('mukurtu_charset_test'), (string) $requirement['description']);
  }

  /**
   * A single utf8mb3 column is reported even when its table is utf8mb4.
   *
   * This is the shape search_api_db left behind: a utf8mb4 text table whose
   * item_id column was utf8mb3.
   */
  public function testWarningForSingleColumnInUtf8mb4Table(): void {
    $database = \Drupal::database();
    $database->query("CREATE TABLE {mukurtu_charset_column_test} ([item_id] VARCHAR(150) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci' NOT NULL, [word] VARCHAR(50) NOT NULL) CHARACTER SET 'utf8mb4' COLLATE 'utf8mb4_bin'");
    $table = $this->fullTableName('mukurtu_charset_column_test');

    $collation = (string) $database->query('SELECT TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table', [
      ':table' => $table,
    ])->fetchField();
    $this->assertSame('utf8mb4_bin', $collation, 'The table default is utf8mb4.');

    $requirement = $this->requirements()[static::KEY];

    $this->assertSame(RequirementSeverity::Warning, $requirement['severity']);
    $this->assertStringContainsString($table, (string) $requirement['description']);
  }

  /**
   * The count covers every affected table, and other tables aren't listed.
   */
  public function testWarningCountsEachTable(): void {
    $database = \Drupal::database();
    $database->query("CREATE TABLE {mukurtu_charset_test_a} ([value] VARCHAR(255) NOT NULL) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'");
    $database->query("CREATE TABLE {mukurtu_charset_test_b} ([value] VARCHAR(255) NOT NULL) CHARACTER SET 'latin1'");

    $requirement = $this->requirements()[static::KEY];

    $this->assertSame("2 tables don't use utf8mb4", (string) $requirement['value']);
    $description = (string) $requirement['description'];
    $this->assertStringContainsString($this->fullTableName('mukurtu_charset_test_a'), $description);
    $this->assertStringContainsString($this->fullTableName('mukurtu_charset_test_b'), $description);
    $this->assertStringNotContainsString($this->fullTableName('users_data'), $description);
  }

  /**
   * Only the first 10 tables are named; the rest are summarized as a count.
   */
  public function testLongListIsSummarized(): void {
    $database = \Drupal::database();
    for ($i = 1; $i <= 12; $i++) {
      $database->query(sprintf("CREATE TABLE {mukurtu_charset_test_%02d} ([value] VARCHAR(255) NOT NULL) CHARACTER SET 'utf8' COLLATE 'utf8_general_ci'", $i));
    }

    $requirement = $this->requirements()[static::KEY];

    $this->assertSame("12 tables don't use utf8mb4", (string) $requirement['value']);
    $description = (string) $requirement['description'];
    $this->assertStringContainsString($this->fullTableName('mukurtu_charset_test_10') . ', and 2 more.', $description);
    $this->assertStringNotContainsString($this->fullTableName('mukurtu_charset_test_11'), $description);
    $this->assertStringNotContainsString($this->fullTableName('mukurtu_charset_test_12'), $description);
  }

}
