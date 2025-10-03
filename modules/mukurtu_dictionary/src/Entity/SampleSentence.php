<?php

namespace Drupal\mukurtu_dictionary\Entity;

use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\mukurtu_dictionary\Entity\SampleSentenceInterface;
use Drupal\Core\Entity\EntityStorageInterface;

class SampleSentence extends Paragraph implements SampleSentenceInterface {

  public static function bundleFieldDefinitions(EntityTypeInterface $entity_type, $bundle, array $base_field_definitions) {
    $definitions = [];

    $definitions['field_sentence'] = BaseFieldDefinition::create('string_long')
      ->setLabel(t('Sample Sentence'))
      ->setDescription(t('Text entry of the sample sentence. </br>Maximum 255 characters.'))
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(FALSE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    $definitions['field_sentence_recording'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(t('Recording'))
      ->setDescription('Audio recording of the sample sentence. Recordings can be assigned a different cultural protocol from the dictionary word to allow differential access to the recordings and metadata.	</br>Select ""Add media"" to select or upload an audio file. </br>Supported file formats: MP3, M4A, WAV, AAC, OGG. </br>Note that the audio file itself includes a contributor field that can be used to record and indicate the name of the speaker.')
      ->setSettings([
        'target_type' => 'media',
        'handler' => 'default:media',
        'handler_settings' => [
          'target_bundles' => [
            'audio' => 'audio',
          ],
          'auto_create' => FALSE,
        ]
      ])
      ->setCardinality(1)
      ->setRequired(FALSE)
      ->setRevisionable(TRUE)
      ->setTranslatable(TRUE)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', TRUE);

    return $definitions;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    // If the sample sentence field is empty but the sentence recording is not,
    // autofill the sample sentence field with the recording transcription.
    $fieldSentenceRecordingValue = $this->get('field_sentence_recording')->getValue() ?? NULL;
    $fieldSentenceValue = $this->get('field_sentence')->getValue() ?? NULL;
    if ($fieldSentenceRecordingValue && !$fieldSentenceValue) {
      $audioEntityId = $fieldSentenceRecordingValue[0]['target_id'];
      $audioEntity = \Drupal::entityTypeManager()->getStorage('media')->load($audioEntityId);
      if ($audioEntity) {
        $transcriptionField = $audioEntity->get('field_transcription')->getValue() ?? NULL;
        if ($transcriptionField) {
          $transcriptionValue = $transcriptionField[0]['value'] ?? NULL;
          if ($transcriptionValue) {
            $this->set('field_sentence', strip_tags($transcriptionValue));
          }
        }
      }
    }
  }
}
