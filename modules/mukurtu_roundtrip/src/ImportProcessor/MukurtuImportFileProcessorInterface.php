<?php

namespace Drupal\mukurtu_roundtrip\ImportProcessor;

use Drupal\file\Entity\File;
use Drupal\mukurtu_roundtrip\ImportProcessor\MukurtuImportFileProcessorResult;

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
   * Split a file into pieces for batch processing.
   *
   * @param \Drupal\file\Entity\File $file
   *   The full input file to split.
   * @param int $size
   *   The number of items to include in each piece.
   *
   * @return array
   *   Returns an array of File IDs.
   */
  public function chunkForBatch(File $file, int $size);

  /**
   * Process the given file.
   *
   * @param \Drupal\file\Entity\File $file
   *   The file entity to process.
   * @param array $context
   *   The importer context.
   *
   * @return MukurtuImportFileProcessorResult[]
   *   Return the processed file.
   */
  public static function process(File $file, array $context = []);

}
