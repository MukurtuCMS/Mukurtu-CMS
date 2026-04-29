<?php

declare(strict_types=1);

namespace Drupal\mukurtu_import\Plugin\migrate\process;

use DOMDocument;
use DOMXPath;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves media embed references in formatted text fields during import.
 *
 * Three syntaxes are supported:
 *
 * 1. Full drupal-media tag with data-entity-name (resolves by media label):
 * @code
 * <drupal-media data-entity-type="media" data-entity-name="My Image" data-view-mode="default">
 * @endcode
 *
 * 2. Full drupal-media tag with data-entity-filename (resolves by source file
 *    filename):
 * @code
 * <drupal-media data-entity-type="media" data-entity-filename="my-image.jpg" data-view-mode="default">
 * @endcode
 *
 * 3. Curly-brace shortcode (tries name first, then filename):
 * @code
 * {{media:My Image}}
 * {{media:my-image.jpg}}
 * @endcode
 *
 * 4. Square-bracket shortcode with optional attributes:
 * @code
 * [media name="My Image"]
 * [media filename="my-image.jpg" view-mode="thumbnail" align="center"]
 * @endcode
 *
 * Supported square-bracket attributes: name, filename, view-mode, align,
 * caption, alt.
 *
 * All references are resolved to a proper data-entity-uuid and the temporary
 * attributes/shortcodes are removed before the content is saved. Existing
 * data-entity-uuid tags are passed through unchanged.
 *
 * @MigrateProcessPlugin(
 *   id = "mukurtu_resolve_media_embeds",
 *   handle_multiples = FALSE
 * )
 */
class ResolveMediaEmbeds extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a ResolveMediaEmbeds object.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (empty($value) || !is_string($value)) {
      return $value;
    }

    $has_drupal_media_attrs = str_contains($value, 'data-entity-name') || str_contains($value, 'data-entity-filename');
    $has_shortcode = str_contains($value, '{{media:') || str_contains($value, '[media ');

    if (!$has_drupal_media_attrs && !$has_shortcode) {
      return $value;
    }

    if ($has_shortcode) {
      $value = $this->convertShortcodes($value);
    }

    // After shortcode conversion, bail out if there is nothing for the DOM
    // to resolve.
    if (
      !str_contains($value, 'data-entity-name') &&
      !str_contains($value, 'data-entity-filename') &&
      !str_contains($value, 'data-entity-identifier')
    ) {
      return $value;
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    // Prefix with an XML encoding declaration so DOMDocument parses the input
    // as UTF-8 rather than its default ISO-8859-1, which would corrupt any
    // multi-byte characters (e.g. curly quotes, accented letters).
    $doc->loadHTML('<?xml encoding="utf-8"?>' . $value, LIBXML_HTML_NODEFDTD);
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($doc);
    $media_elements = $xpath->query(
      '//drupal-media[@data-entity-name or @data-entity-filename or @data-entity-identifier]'
    );

    if ($media_elements->length === 0) {
      return $value;
    }

    $resolved = FALSE;
    foreach ($media_elements as $element) {
      $uuid = NULL;

      if ($name = $element->getAttribute('data-entity-name')) {
        try {
          $uuid = $this->resolveByName($name);
        }
        catch (MigrateException $e) {
          $migrate_executable->saveMessage($e->getMessage());
          continue;
        }
        if (!$uuid) {
          $migrate_executable->saveMessage(sprintf('Could not find a media entity with name "%s".', $name));
          continue;
        }
        $element->setAttribute('data-entity-uuid', $uuid);
        $element->removeAttribute('data-entity-name');
        $resolved = TRUE;
      }
      elseif ($filename = $element->getAttribute('data-entity-filename')) {
        try {
          $uuid = $this->resolveByFilename($filename);
        }
        catch (MigrateException $e) {
          $migrate_executable->saveMessage($e->getMessage());
          continue;
        }
        if (!$uuid) {
          $migrate_executable->saveMessage(sprintf('Could not find a media entity with filename "%s".', $filename));
          continue;
        }
        $element->setAttribute('data-entity-uuid', $uuid);
        $element->removeAttribute('data-entity-filename');
        $resolved = TRUE;
      }
      elseif ($identifier = $element->getAttribute('data-entity-identifier')) {
        // Try name first; fall back to filename only if nothing matched.
        try {
          $uuid = $this->resolveByName($identifier);
          if ($uuid === NULL) {
            $uuid = $this->resolveByFilename($identifier);
          }
        }
        catch (MigrateException $e) {
          $migrate_executable->saveMessage($e->getMessage());
          continue;
        }
        if (!$uuid) {
          $migrate_executable->saveMessage(sprintf('Could not find a media entity matching "%s".', $identifier));
          continue;
        }
        $element->setAttribute('data-entity-uuid', $uuid);
        $element->removeAttribute('data-entity-identifier');
        $resolved = TRUE;
      }
    }

    if ($resolved) {
      $new_value = $doc->saveHTML();
      $strip_these_tags = ['<?xml encoding="utf-8"?>', '<html>', '</html>', '<body>', '</body>'];
      $value = str_replace($strip_these_tags, '', $new_value);
    }

    return $value;
  }

  /**
   * Convert shortcode syntax to intermediate drupal-media tags.
   *
   * Handles:
   *   {{media:VALUE}} → data-entity-identifier (resolved by name then filename)
   *   [media name="..." filename="..." view-mode="..." align="..." caption="..." alt="..."]
   *
   * @param string $value
   *   The raw text value.
   *
   * @return string
   *   The value with shortcodes replaced by drupal-media tags.
   */
  protected function convertShortcodes(string $value): string {
    // {{media:VALUE}} shortcode.
    $value = preg_replace_callback(
      '/\{\{media:([^}]+)\}\}/i',
      function (array $m): string {
        $identifier = htmlspecialchars(trim($m[1]), ENT_QUOTES, 'UTF-8');
        return '<drupal-media data-entity-type="media" data-entity-identifier="' . $identifier . '" data-view-mode="default">&nbsp;</drupal-media>';
      },
      $value
    );

    // [media attr="value" ...] shortcode.
    $attr_map = [
      'name'      => 'data-entity-name',
      'filename'  => 'data-entity-filename',
      'view-mode' => 'data-view-mode',
      'align'     => 'data-align',
      'caption'   => 'data-caption',
      'alt'       => 'alt',
    ];

    $value = preg_replace_callback(
      '/\[media\s+([^\]]+)\]/i',
      function (array $m) use ($attr_map): string {
        // Normalize curly/smart quotes to straight quotes so CSV editors that
        // auto-convert punctuation don't break attribute parsing.
        $attrs_str = str_replace(
          ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"],
          ['"',        '"',        "'",         "'"],
          $m[1]
        );
        preg_match_all('/(\w[\w-]*)\s*=\s*(?:"([^"]*?)"|\'([^\']*?)\')/i', $attrs_str, $pairs, PREG_SET_ORDER);

        $tag_attrs = ['data-entity-type="media"'];
        $has_view_mode = FALSE;

        foreach ($pairs as $pair) {
          $key = strtolower($pair[1]);
          $val = $pair[2] !== '' ? $pair[2] : $pair[3];
          if (isset($attr_map[$key])) {
            $tag_attrs[] = $attr_map[$key] . '="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"';
            if ($key === 'view-mode') {
              $has_view_mode = TRUE;
            }
          }
        }

        if (!$has_view_mode) {
          $tag_attrs[] = 'data-view-mode="default"';
        }

        return '<drupal-media ' . implode(' ', $tag_attrs) . '>&nbsp;</drupal-media>';
      },
      $value
    );

    return $value;
  }

  /**
   * Resolve a media entity UUID by the media entity's name/label.
   *
   * @param string $name
   *   The media entity name (label).
   *
   * @return string|null
   *   The UUID of the matching media entity, or NULL if not found.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Thrown when the name matches multiple media entities.
   */
  protected function resolveByName(string $name): ?string {
    $results = $this->entityTypeManager->getStorage('media')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', $name)
      ->execute();

    if (empty($results)) {
      return NULL;
    }
    if (count($results) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple media entities share this name. Try using data-entity-uuid instead.', $name));
    }

    $media = $this->entityTypeManager->getStorage('media')->load(reset($results));
    return $media ? $media->uuid() : NULL;
  }

  /**
   * Resolve a media entity UUID by the filename of its source file.
   *
   * @param string $filename
   *   The filename of the media source file (e.g. "my-image.jpg").
   *
   * @return string|null
   *   The UUID of the matching media entity, or NULL if not found.
   *
   * @throws \Drupal\migrate\MigrateException
   *   Thrown when the filename matches multiple media entities.
   */
  protected function resolveByFilename(string $filename): ?string {
    $file_ids = $this->entityTypeManager->getStorage('file')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('filename', $filename)
      ->execute();

    if (empty($file_ids)) {
      return NULL;
    }

    $file_ids = array_values($file_ids);
    $media_storage = $this->entityTypeManager->getStorage('media');
    $media_type_storage = $this->entityTypeManager->getStorage('media_type');

    $matches = [];
    foreach ($media_type_storage->loadMultiple() as $media_type) {
      $source_plugin = $media_type->getSource();
      $source_field_def = $source_plugin->getSourceFieldDefinition($media_type);
      if (!$source_field_def) {
        continue;
      }
      $source_field_name = $source_field_def->getName();

      $results = $media_storage->getQuery()
        ->accessCheck(FALSE)
        ->condition('bundle', $media_type->id())
        ->condition($source_field_name, $file_ids, 'IN')
        ->execute();

      foreach ($results as $mid) {
        $matches[$mid] = TRUE;
      }
    }

    if (empty($matches)) {
      return NULL;
    }
    if (count($matches) > 1) {
      throw new MigrateException(sprintf('"%s" is ambiguous, multiple media entities reference a file with this name. Try using data-entity-uuid instead.', $filename));
    }

    $media = $media_storage->load(array_key_first($matches));
    return $media ? $media->uuid() : NULL;
  }

}
