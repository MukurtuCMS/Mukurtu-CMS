<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media\Plugin\Field\FieldWidget;

use Drupal\Core\Field\Attribute\FieldWidget;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\file\Element\ManagedFile;
use Drupal\file\Plugin\Field\FieldWidget\FileWidget;

/**
 * File upload widget that also asks for each track's language and kind.
 */
#[FieldWidget(
  id: 'mukurtu_video_track',
  label: new TranslatableMarkup('Video caption track'),
  field_types: ['mukurtu_video_track'],
)]
class VideoTrackWidget extends FileWidget {

  /**
   * {@inheritdoc}
   */
  public static function value($element, $input, FormStateInterface $form_state) {
    $return = parent::value($element, $input, $form_state);
    $return += [
      'language_target_id' => NULL,
      'kind' => 'captions',
    ];
    return $return;
  }

  /**
   * {@inheritdoc}
   */
  public static function process($element, FormStateInterface $form_state, $form) {
    $item = $element['#value'];
    $has_file = !empty($element['fids']['#value']);

    $element['language_target_id'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Language'),
      '#description' => new TranslatableMarkup('The language spoken or written in this track. Add a language code to the language term so browsers can identify it.'),
      '#options' => static::languageOptions(),
      '#default_value' => $item['language_target_id'] ?? NULL,
      '#required' => TRUE,
      '#required_error' => new TranslatableMarkup('Select a language for this track.'),
      '#access' => $has_file,
      '#element_validate' => [[static::class, 'validateRequiredFields']],
    ];
    $element['kind'] = [
      '#type' => 'select',
      '#title' => new TranslatableMarkup('Type'),
      '#options' => [
        'captions' => new TranslatableMarkup('Captions (dialogue and other sounds, for viewers who are deaf or hard of hearing)'),
        'subtitles' => new TranslatableMarkup('Subtitles (dialogue only, usually a translation)'),
      ],
      '#default_value' => $item['kind'] ?? 'captions',
      '#required' => TRUE,
      '#access' => $has_file,
    ];

    return parent::process($element, $form_state, $form);
  }

  /**
   * Skips required-field errors while a file is being uploaded or removed.
   *
   * Mirrors ImageWidget::validateRequiredFields(), so adding a second track
   * doesn't fail because the first one has no language yet.
   */
  public static function validateRequiredFields($element, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    if (!empty($triggering_element['#submit']) && in_array([ManagedFile::class, 'submit'], $triggering_element['#submit'], TRUE)) {
      $form_state->setLimitValidationErrors([]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    $values = parent::massageFormValues($values, $form, $form_state);
    foreach ($values as &$value) {
      $value['language_target_id'] = !empty($value['language_target_id']) ? (int) $value['language_target_id'] : NULL;
    }
    return $values;
  }

  /**
   * Builds the language options from the Language vocabulary.
   *
   * @return array<int, string>
   *   Term labels in the current content language, keyed by term ID.
   */
  protected static function languageOptions(): array {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('vid', 'language')
      ->sort('name')
      ->execute();
    $repository = \Drupal::service('entity.repository');
    $options = [];
    foreach ($storage->loadMultiple($ids) as $id => $term) {
      $options[$id] = $repository->getTranslationFromContext($term)->label();
    }
    natcasesort($options);
    return $options;
  }

}
