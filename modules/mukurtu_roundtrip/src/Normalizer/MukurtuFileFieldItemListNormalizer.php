<?php

namespace Drupal\mukurtu_roundtrip\Normalizer;

use Drupal\serialization\Normalizer\ComplexDataNormalizer;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Drupal\file\Entity\File;

class MukurtuFileFieldItemListNormalizer extends ComplexDataNormalizer implements NormalizerInterface, DenormalizerInterface {

  protected $supportedInterfaceOrClass = 'Drupal\file\Plugin\Field\FieldType\FileFieldItemList';

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization($data, $type, $format = NULL) {
    if ($type != 'Drupal\file\Plugin\Field\FieldType\FileFieldItemList') {
      return FALSE;
    }
    return parent::supportsDenormalization($data, $type, $format);
  }

  /**
   * {@inheritdoc}
   */
  public function denormalize($data, $class, $format = NULL, array $context = []) {
    if (!isset($context['target_instance'])) {
      throw new InvalidArgumentException('$context[\'target_instance\'] must be set to denormalize with the MukurtuFileFieldItemListNormalizer');
    }
    if ($context['target_instance']->getParent() == NULL) {
      throw new InvalidArgumentException('The field item passed in via $context[\'target_instance\'] must have a parent set.');
    }

    $field_item = $context['target_instance'];
    //dpm($field_item->getSettings());


    // TODO: This is not at all generalized.
    $item = [];
    $file = File::load($data['target_id']);
    if ($file) {
      $item['title'] = $file->filename->value;
      $item['alt'] = '';
      $item['target_id'] = $data['target_id'];
    }

    $field_item->setValue($item);

    return $field_item;
  }

}
