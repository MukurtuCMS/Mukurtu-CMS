<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Plugin\Field\FieldFormatter;

use Drupal\Component\Utility\Html;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Template\Attribute;
use Drupal\file\FileInterface;
use Drupal\file\Plugin\Field\FieldFormatter\FileVideoFormatter;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the video with a <track> for each caption or subtitle file.
 *
 * Tracks come from the media entity's field_media_video_tracks field.
 */
#[FieldFormatter(
  id: 'mukurtu_video_with_tracks',
  label: new TranslatableMarkup('Video with caption tracks'),
  description: new TranslatableMarkup('Display the file using an HTML5 video tag, with caption and subtitle tracks.'),
  field_types: [
    'file',
  ],
)]
class VideoWithTracksFormatter extends FileVideoFormatter {

  /**
   * The field that holds the caption and subtitle tracks.
   */
  public const TRACKS_FIELD = 'field_media_video_tracks';

  /**
   * The entity repository.
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The taxonomy term storage.
   */
  protected EntityStorageInterface $termStorage;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->entityRepository = $container->get('entity.repository');
    $instance->termStorage = $container->get('entity_type.manager')->getStorage('taxonomy_term');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = parent::viewElements($items, $langcode);
    if (empty($elements)) {
      return $elements;
    }

    $cacheability = new CacheableMetadata();
    $cacheability->addCacheContexts(['languages:language_interface']);
    $tracks = $this->buildTracks($items, $cacheability);

    foreach ($elements as &$element) {
      $element['#theme'] = 'mukurtu_file_video';
      $element['#tracks'] = $tracks;
      $cacheability->applyTo($element);
    }

    return $elements;
  }

  /**
   * Builds the attributes for each track on the video's media entity.
   *
   * @return \Drupal\Core\Template\Attribute[]
   *   One attribute set per track, in field order.
   */
  protected function buildTracks(FieldItemListInterface $items, CacheableMetadata $cacheability): array {
    $entity = $items->getEntity();
    if (!$entity->hasField(self::TRACKS_FIELD)) {
      return [];
    }

    $tracks = [];
    foreach ($entity->get(self::TRACKS_FIELD) as $item) {
      $file = $item->entity;
      if (!$file instanceof FileInterface) {
        continue;
      }
      $cacheability->addCacheableDependency($file);

      $kind = $item->kind === 'subtitles' ? 'subtitles' : 'captions';
      $attributes = new Attribute([
        'kind' => $kind,
        'src' => $file->createFileUrl(),
      ]);

      $term = $item->language_target_id ? $this->termStorage->load($item->language_target_id) : NULL;
      if ($term instanceof TermInterface) {
        $cacheability->addCacheableDependency($term);
        $code = $term->hasField('field_language_code') ? trim((string) $term->get('field_language_code')->value) : '';
        if ($code !== '') {
          $attributes->setAttribute('srclang', $code);
        }
        $language = $this->entityRepository->getTranslationFromContext($term)->label();
        if ($kind === 'captions') {
          // Attribute escapes the value itself, so undo the placeholder's
          // escaping to keep names like "Lil'wat" intact.
          $language = Html::decodeEntities((string) $this->t('@language (captions)', ['@language' => $language]));
        }
        $attributes->setAttribute('label', $language);
      }

      $tracks[] = $attributes;
    }
    return $tracks;
  }

}
