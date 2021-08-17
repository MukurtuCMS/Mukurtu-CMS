<?php

namespace Drupal\mukurtu_roundtrip\ImportProcessor;

use Drupal\file\Entity\File;

interface MukurtuImportFileProcessorInterface {

  /**
   * Returns the friendly name of the processor.
   *
   * @return string
   *   Plain text name of the processor.
   */
  public static function getName();

  /**
   * Returns the machine name of the processor.
   *
   * @return string
   *   Machine name of the processor.
   */
  public static function id();

  /**
   * Checks if the processor supports a given file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity to check support for.
   *
   * @return bool
   *   Returns true if supported, false if not supported.
   */
  public function supportsFile(File $file);

  /**
   * Process the given file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity to process.
   * @param array $context
   *   The importer context.
   *
   * @return \Drupal\file\Entity\File
   *   Return the processed file.
   */
  public function process(File $file, array $context = []);

}
