<?php

namespace Drupal\mukurtu_export;

/**
 * Encodes/decodes export item keys.
 *
 * An item key is either a bare entity ID (export the entity's original
 * language, today's behavior) or a composite "$id:$langcode" string
 * (export a specific translation). Every producer of the entities array
 * consumed by MukurtuExporter plugins (AdHocExporterSource,
 * ExportListSource) builds keys through encode() so the format is defined
 * exactly once.
 */
class ExportItemIdentity {

  /**
   * Builds an item key from an entity ID and an optional langcode.
   *
   * @param int|string $id
   *   The entity ID.
   * @param string|null $langcode
   *   The requested translation's langcode, or NULL/empty for the entity's
   *   original language.
   *
   * @return string
   *   The item key.
   */
  public static function encode(int|string $id, ?string $langcode = NULL): string {
    return $langcode ? "{$id}:{$langcode}" : (string) $id;
  }

  /**
   * Splits an item key back into its entity ID and langcode.
   *
   * @param string $key
   *   The item key, as produced by encode().
   *
   * @return array
   *   A 2-tuple: [id, langcode]. langcode is NULL when the key carried no
   *   language (the entity's original-language export).
   */
  public static function decode(string $key): array {
    $parts = explode(':', $key, 2);
    return [$parts[0], $parts[1] ?? NULL];
  }

}
