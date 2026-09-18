<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Form\ImportFileSummaryForm;

/**
 * Test double that makes the wizard's uploaded files visible to a kernel test.
 *
 * The real getMetadataFiles() runs an access-checked entity query. A private,
 * not-yet-referenced file is invisible to that query outside a real request,
 * so buildForm() would render zero rows and there would be nothing to assert
 * against. Only the query is replaced; the weight filtering, the row building
 * and the AJAX callbacks all run as shipped.
 */
class TestImportFileSummaryForm extends ImportFileSummaryForm {

  /**
   * File IDs to report as the current import's uploaded metadata files.
   *
   * @var int[]
   */
  public array $testMetadataFids = [];

  /**
   * {@inheritdoc}
   */
  public function getMetadataFiles(): array {
    return $this->testMetadataFids;
  }

}
