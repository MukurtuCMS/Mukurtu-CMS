<?php

/**
 * @file
 * Contains \Drupal\mukurtu_roundtrip\Plugin\Block\ImportFromFileBlock.
 */

namespace Drupal\mukurtu_roundtrip\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormInterface;

/**
 * Provides an 'ImportFromFileBlock' block.
 *
 * @Block(
 *   id = "mukurtu_import_from_file_block",
 *   admin_label = @Translation("Mukurtu - Import From File"),
 *   category = @Translation("Mukurtu Roundtrip")
 * )
 */
class ImportFromFileBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build() {

    $form = \Drupal::formBuilder()->getForm('Drupal\mukurtu_roundtrip\Form\ImportFromFileForm');

    return $form;
  }
}
