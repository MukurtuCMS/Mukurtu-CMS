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
 * Resolves drupal-media embed tags using media name or filename attributes.
 *
 * During CSV import, users can reference media assets in formatted text fields
 * using human-readable identifiers instead of UUIDs:
 *
 * By media entity name:
 * @code
 * <drupal-media data-entity-type="media" data-entity-name="My Image" data-view-mode="default">
 * @endcode
 *
 * By source file filename:
 * @code
 * <drupal-media data-entity-type="media" data-entity-filename="my-image.jpg" data-view-mode="default">
 * @endcode
 *
 * Both attributes are resolved to a proper data-entity-uuid and removed from
 * the tag before the content is saved.
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

    // Quick check: only parse HTML if there's a drupal-media tag with one of
    // the custom lookup attributes present.
    if (
      !str_contains($value, 'data-entity-name') &&
      !str_contains($value, 'data-entity-filename')
    ) {
      return $value;
    }

    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(TRUE);
    $doc->loadHTML($value, LIBXML_HTML_NODEFDTD);
    libxml_use_internal_errors($previous);
    $xpath = new DOMXPath($doc);
    $media_elements = $xpath->query('//drupal-media[@data-entity-name or @data-entity-filename]');

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
    }

    if ($resolved) {
      $new_value = $doc->saveHTML();
      $strip_these_tags = ['<html>', '</html>', '<body>', '</body>'];
      $value = str_replace($strip_these_tags, '', $new_value);
    }

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
