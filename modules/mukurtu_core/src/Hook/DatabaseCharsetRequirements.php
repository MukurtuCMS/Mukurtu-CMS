<?php

declare(strict_types=1);

namespace Drupal\mukurtu_core\Hook;

use Drupal\Core\Database\Connection;
use Drupal\Core\Extension\Requirement\RequirementSeverity;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Flags database tables with text columns that cannot hold 4-byte characters.
 *
 * Drupal core creates every MySQL table as utf8mb4, so changing the database's
 * default character set protects nothing: the risk is a module converting its
 * own tables afterwards. Search API's database backend did exactly that,
 * converting its tables to utf8mb3, so any item containing Osage, Adlam, emoji
 * or another 4-byte character was rejected by MySQL and silently dropped out
 * of the index (#2323).
 *
 * Columns are checked rather than table defaults, because a single column can
 * differ from its table: search_api_db's text table is utf8mb4 but its item_id
 * column was utf8mb3. ascii is allowed alongside utf8mb4, since core
 * deliberately stores some identifiers, such as cache IDs, as ascii.
 */
class DatabaseCharsetRequirements {

  use StringTranslationTrait;

  /**
   * Character sets that are expected on a Drupal text column.
   */
  protected const ALLOWED_CHARACTER_SETS = ['utf8mb4', 'ascii'];

  /**
   * How many table names to list before summarizing the rest as a count.
   *
   * A site still on unpatched search_api_db has 80 or more affected tables,
   * which would bury the rest of the status report.
   */
  protected const MAX_LISTED_TABLES = 10;

  public function __construct(
    protected readonly Connection $connection,
  ) {}

  /**
   * Implements hook_runtime_requirements().
   */
  #[Hook('runtime_requirements')]
  public function runtimeRequirements(): array {
    if ($this->connection->databaseType() !== 'mysql') {
      return [];
    }

    $tables = $this->tablesWithOtherCharacterSets();

    if (empty($tables)) {
      return [
        'mukurtu_core_database_charset' => [
          'title' => $this->t('Database character set'),
          'value' => $this->t('All tables use utf8mb4'),
          'severity' => RequirementSeverity::OK,
        ],
      ];
    }

    return [
      'mukurtu_core_database_charset' => [
        'title' => $this->t('Database character set'),
        'value' => $this->formatPlural(count($tables), "1 table doesn't use utf8mb4", "@count tables don't use utf8mb4"),
        'description' => $this->t("Some text columns in these tables use a character set other than utf8mb4, so they can't store characters such as Osage, Adlam, and emoji. Content that contains those characters can fail to save or be left out of search results. Back up the database, then convert each table to utf8mb4, or ask your hosting provider to. Tables: @tables.", [
          '@tables' => $this->tableList($tables),
        ]),
        'severity' => RequirementSeverity::Warning,
      ],
    ];
  }

  /**
   * Formats table names for the description, summarizing any past the limit.
   *
   * @param string[] $tables
   *   Table names.
   *
   * @return string
   *   A comma-separated list.
   */
  protected function tableList(array $tables): string {
    $listed = implode(', ', array_slice($tables, 0, static::MAX_LISTED_TABLES));
    $remaining = count($tables) - static::MAX_LISTED_TABLES;
    if ($remaining <= 0) {
      return $listed;
    }

    return (string) $this->formatPlural($remaining, '@tables, and 1 more', '@tables, and @count more', [
      '@tables' => $listed,
    ]);
  }

  /**
   * Returns this site's tables that have a text column in another charset.
   *
   * @return string[]
   *   Table names, including the site's table prefix, in alphabetical order.
   */
  protected function tablesWithOtherCharacterSets(): array {
    // Views also appear in information_schema.COLUMNS, but they inherit their
    // columns from base tables, so only base tables are reported.
    return $this->connection->query(
      "SELECT DISTINCT c.TABLE_NAME
        FROM information_schema.COLUMNS c
        INNER JOIN information_schema.TABLES t
          ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME
        WHERE c.TABLE_SCHEMA = DATABASE()
          AND t.TABLE_TYPE = 'BASE TABLE'
          AND c.TABLE_NAME LIKE :prefix
          AND c.CHARACTER_SET_NAME IS NOT NULL
          AND c.CHARACTER_SET_NAME NOT IN (:allowed[])
        ORDER BY c.TABLE_NAME",
      [
        ':prefix' => $this->connection->escapeLike($this->connection->getPrefix()) . '%',
        ':allowed[]' => static::ALLOWED_CHARACTER_SETS,
      ],
    )->fetchCol();
  }

}
