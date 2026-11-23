<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Form\ExecuteImportForm;

/**
 * Test double that makes the wizard's uploaded files visible to a kernel test.
 *
 * The real getMetadataFiles() runs an access-checked entity query that can't
 * see an unreferenced private file outside a real request. Only the query is
 * replaced; submitForm() runs as shipped.
 *
 * @see \Drupal\Tests\mukurtu_import\Kernel\TestImportFileSummaryForm
 */
class TestExecuteImportForm extends ExecuteImportForm {

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
