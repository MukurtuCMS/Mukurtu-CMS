<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu\Unit;

use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Yaml\Yaml;

/**
 * Asserts that what Mukurtu exports is what Mukurtu can import.
 *
 * The CSV exporters and the import strategies are joined only by strings. An
 * exporter writes a column header, and an import strategy auto-maps a column by
 * matching that header against its own "source" value. Nothing enforces the
 * match, so renaming a header on one side silently breaks round tripping: the
 * export still succeeds, the import still succeeds, and the column just quietly
 * fails to map.
 *
 * Six kernel tests each pinned one corner of this by running an update hook and
 * asserting the mapping it produced. Between them they never stated the actual
 * contract, which turns out to hold almost everywhere: of the 39 bundles present
 * in both an exporter and an import strategy, 38 have every export header
 * matched by an import source. user__user is the single exception, and its
 * unmatched headers are enumerated below rather than left implicit.
 *
 * A pure filesystem and YAML check, so no Drupal bootstrap is needed.
 */
#[Group('mukurtu')]
#[Group('mukurtu_export')]
#[Group('mukurtu_import')]
class ShippedExportImportContractTest extends UnitTestCase {

  /**
   * Export headers on the user exporter with deliberately no import source.
   *
   * These are read-only or system-managed properties. Letting a spreadsheet set
   * them on import is either meaningless or actively harmful, so the importer
   * does not offer them as targets even though the exporter emits them.
   */
  private const USER_EXPORT_ONLY_HEADERS = [
    'Created',
    'Language (langcode)',
    'Timezone',
    'Receive Email Notifications',
    'Profile Picture File',
    'Profile Picture Alt Text',
  ];

  /**
   * A known gap, listed so it is visible rather than silently tolerated.
   *
   * The external exporters emit a UUID column for 45 bundles, because UUID is
   * the identifier that survives moving content between sites, which is what
   * those exporters are for. The local exporters emit it for user__user only.
   *
   * Every import strategy accepts a UUID source except the 16 taxonomy term
   * ones. So an external export of taxonomy terms produces a UUID column that
   * cannot be mapped on import, which is precisely the round trip external
   * export exists to support.
   *
   * This is a pre-existing defect, not a deliberate exemption. It is enumerated
   * here so the rest of the contract can be enforced strictly, and so the list
   * shrinks visibly when the gap is closed. Do not add entries to this list to
   * make a new failure go away.
   */
  private const KNOWN_UNMAPPABLE = [
    'taxonomy_term__category' => ['UUID'],
    'taxonomy_term__community_type' => ['UUID'],
    'taxonomy_term__contributor' => ['UUID'],
    'taxonomy_term__creator' => ['UUID'],
    'taxonomy_term__format' => ['UUID'],
    'taxonomy_term__interpersonal_relationship' => ['UUID'],
    'taxonomy_term__keywords' => ['UUID'],
    'taxonomy_term__language' => ['UUID'],
    'taxonomy_term__location' => ['UUID'],
    'taxonomy_term__media_tag' => ['UUID'],
    'taxonomy_term__people' => ['UUID'],
    'taxonomy_term__place_type' => ['UUID'],
    'taxonomy_term__publisher' => ['UUID'],
    'taxonomy_term__subject' => ['UUID'],
    'taxonomy_term__type' => ['UUID'],
    'taxonomy_term__word_type' => ['UUID'],
  ];

  /**
   * Resolves the profile root from this file's location.
   */
  private static function profileRoot(): string {
    return dirname(__DIR__, 3);
  }

  /**
   * Every shipped CSV exporter, keyed by its short name.
   */
  private static function exporters(): array {
    $exporters = [];
    $pattern = self::profileRoot() . '/modules/mukurtu_export/config/install/mukurtu_export.csv_exporter.*.yml';

    foreach (glob($pattern) ?: [] as $path) {
      $name = str_replace(['mukurtu_export.csv_exporter.', '.yml'], '', basename($path));
      $exporters[$name] = Yaml::parseFile($path)['entity_fields_export_list'] ?? [];
    }

    return $exporters;
  }

  /**
   * Every shipped import strategy, keyed by "entity_type__bundle".
   */
  private static function strategies(): array {
    $strategies = [];
    $pattern = self::profileRoot() . '/modules/mukurtu_import/config/install/mukurtu_import.mukurtu_import_strategy.*.yml';

    foreach (glob($pattern) ?: [] as $path) {
      $strategy = Yaml::parseFile($path);
      $key = $strategy['target_entity_type_id'] . '__' . $strategy['target_bundle'];
      $strategies[$key] = [
        'file' => basename($path),
        'mapping' => $strategy['mapping'] ?? [],
      ];
    }

    return $strategies;
  }

  public static function exporterProvider(): \Generator {
    foreach (array_keys(self::exporters()) as $name) {
      yield $name => [$name];
    }
  }

  /**
   * Sanity check on the providers.
   *
   * Every assertion below is driven by a glob. If a glob stopped matching, the
   * data-provided tests would pass while checking nothing.
   */
  public function testTheShippedConfigIsActuallyFound(): void {
    $this->assertCount(4, self::exporters(), 'Expected the four shipped CSV exporters.');
    $this->assertGreaterThanOrEqual(39, count(self::strategies()), 'Expected at least 39 shipped import strategies.');
  }

  /**
   * Every exported column can be imported again.
   *
   * This is the round-trip guarantee: export a CSV, edit it, import it back,
   * and every column still finds its field.
   */
  #[DataProvider('exporterProvider')]
  public function testEveryExportedColumnHasAnImportSource(string $exporterName): void {
    $exporter = self::exporters()[$exporterName];
    $strategies = self::strategies();
    $compared = 0;

    foreach ($exporter as $bundleKey => $headers) {
      if (!is_array($headers) || !isset($strategies[$bundleKey])) {
        continue;
      }
      $compared++;

      $sources = array_column($strategies[$bundleKey]['mapping'], 'source');
      $allowed = $bundleKey === 'user__user' ? self::USER_EXPORT_ONLY_HEADERS : [];
      $allowed = array_merge($allowed, self::KNOWN_UNMAPPABLE[$bundleKey] ?? []);

      foreach (array_unique($headers) as $header) {
        if (in_array($header, $allowed, TRUE)) {
          continue;
        }
        $this->assertContains(
          $header,
          $sources,
          "$exporterName exports '$header' for $bundleKey, but {$strategies[$bundleKey]['file']} has no import source matching it, so that column will not map on import."
        );
      }
    }

    $this->assertGreaterThan(30, $compared, "Only $compared bundles were compared; the exporter and strategy keys have stopped lining up.");
  }

  /**
   * The known-gap list still describes reality.
   *
   * An exception list nobody revisits becomes permission. This fails if an
   * entry is no longer unmappable, so closing the taxonomy UUID gap forces the
   * list to shrink instead of quietly outliving the bug.
   */
  public function testTheKnownGapListIsStillAccurate(): void {
    $strategies = self::strategies();

    foreach (self::KNOWN_UNMAPPABLE as $bundleKey => $headers) {
      $this->assertArrayHasKey($bundleKey, $strategies, "$bundleKey no longer has an import strategy, so its entry in KNOWN_UNMAPPABLE is stale.");
      $sources = array_column($strategies[$bundleKey]['mapping'], 'source');

      foreach ($headers as $header) {
        $this->assertNotContains(
          $header,
          $sources,
          "$bundleKey can now import '$header'. Remove it from KNOWN_UNMAPPABLE so the contract is enforced for it."
        );
      }
    }
  }

  /**
   * The four exporters agree on every shared column header.
   *
   * They differ only in whether media is included and whether files are local
   * or external. A column that exists in more than one must be labelled
   * identically in all of them, or a CSV imports cleanly from one exporter and
   * not from another.
   */
  public function testAllExportersAgreeOnSharedHeaders(): void {
    $exporters = self::exporters();
    $reference = 'default_local_with_media';
    $this->assertArrayHasKey($reference, $exporters);

    foreach ($exporters as $name => $exporter) {
      if ($name === $reference) {
        continue;
      }

      foreach ($exporter as $bundleKey => $headers) {
        if (!is_array($headers) || !isset($exporters[$reference][$bundleKey])) {
          continue;
        }

        foreach ($headers as $field => $header) {
          if (!isset($exporters[$reference][$bundleKey][$field])) {
            continue;
          }
          $this->assertSame(
            $exporters[$reference][$bundleKey][$field],
            $header,
            "$name labels $bundleKey field '$field' differently from $reference."
          );
        }
      }
    }
  }

  /**
   * Cultural protocol columns keep their exact headers.
   *
   * These carry the access control for the row. If the header drifts, the
   * column stops auto-mapping and an import silently lands content with no
   * protocols set, which is a disclosure risk rather than a cosmetic bug.
   */
  #[DataProvider('exporterProvider')]
  public function testCulturalProtocolHeadersAreExact(string $exporterName): void {
    $exporter = self::exporters()[$exporterName];
    $found = 0;

    foreach ($exporter as $bundleKey => $headers) {
      if (!is_array($headers)) {
        continue;
      }

      foreach ($headers as $field => $header) {
        if ($field === 'field_cultural_protocols/protocols') {
          $this->assertSame('Cultural Protocols > Protocols', $header, "$exporterName: wrong header for $bundleKey protocols.");
          $found++;
        }
        if ($field === 'field_cultural_protocols/sharing_setting') {
          $this->assertSame('Cultural Protocols > Sharing Setting', $header, "$exporterName: wrong header for $bundleKey sharing setting.");
          $found++;
        }
      }
    }

    $this->assertGreaterThan(0, $found, "$exporterName exports no cultural protocol columns at all.");
  }

  /**
   * Accounts export a single readable status column.
   *
   * The raw 'status' and 'field_pending' fields were replaced by a computed
   * 'account_status'. Re-exposing the raw pair would give an operator two
   * columns that disagree with the one the importer accepts.
   */
  #[DataProvider('exporterProvider')]
  public function testUserExportUsesComputedAccountStatus(string $exporterName): void {
    $user = self::exporters()[$exporterName]['user__user'] ?? NULL;
    $this->assertIsArray($user, "$exporterName does not export users at all.");

    $this->assertSame('Account Status', $user['account_status'] ?? NULL, "$exporterName: wrong account status header.");
    $this->assertSame('Username', $user['name'] ?? NULL, "$exporterName: wrong username header.");
    $this->assertSame('Email', $user['mail'] ?? NULL, "$exporterName: wrong email header.");

    $this->assertArrayNotHasKey('status', $user, "$exporterName re-exposes the raw status field.");
    $this->assertArrayNotHasKey('field_pending', $user, "$exporterName re-exposes the raw field_pending field.");
  }

  /**
   * Media reference columns are split into their sub-properties.
   *
   * A bare entity reference column exports an opaque id that means nothing to
   * an operator and cannot be re-imported. Each is split into a file id and its
   * alternative text instead, and the bare column must not come back.
   */
  #[DataProvider('exporterProvider')]
  public function testMediaReferenceColumnsAreSplit(string $exporterName): void {
    $exporter = self::exporters()[$exporterName];
    $checked = 0;

    foreach ($exporter as $bundleKey => $headers) {
      if (!is_array($headers)) {
        continue;
      }

      foreach ($headers as $field => $header) {
        if (!str_ends_with((string) $field, '/target_id')) {
          continue;
        }
        $base = substr((string) $field, 0, -strlen('/target_id'));
        if (!str_contains($base, 'image')) {
          continue;
        }

        $checked++;
        $this->assertStringEndsWith(' > File ID', $header, "$exporterName: $bundleKey $field should be labelled as a file id.");
        $this->assertArrayHasKey("$base/alt", $headers, "$exporterName: $bundleKey exports $field with no matching alternative text column.");
        $this->assertStringEndsWith(' > Alternative text', $headers["$base/alt"], "$exporterName: $bundleKey $base/alt is mislabelled.");
        $this->assertArrayNotHasKey($base, $headers, "$exporterName: $bundleKey still exports the bare '$base' column alongside its split sub-columns.");
      }
    }

    $this->assertGreaterThan(0, $checked, "$exporterName exports no split image columns, so this test checked nothing.");
  }

  /**
   * No import strategy maps the raw langcode header.
   *
   * 'Language (langcode)' is what the user exporter emits for its own read-only
   * langcode column. Using it as an import source elsewhere was a recurring
   * mistake, since it looks plausible but is not the header any other exporter
   * writes. Each entity type has its own convention instead: nodes, media,
   * taxonomy terms, communities and protocols use 'Locale', multipage items use
   * 'Language', and paragraphs use 'Language code'.
   */
  public function testNoStrategyUsesTheRawLangcodeHeader(): void {
    foreach (self::strategies() as $bundleKey => $strategy) {
      $sources = array_column($strategy['mapping'], 'source');
      $this->assertNotContains(
        'Language (langcode)',
        $sources,
        "{$strategy['file']} ($bundleKey) maps 'Language (langcode)', which no exporter writes for this entity type."
      );
    }
  }

}
