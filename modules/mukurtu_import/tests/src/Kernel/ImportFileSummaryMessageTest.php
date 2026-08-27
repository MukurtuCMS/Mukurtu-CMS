<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Entity\MukurtuImportStrategy;

/**
 * Tests ImportFileSummaryForm::getMappedFieldsMessage(), which separates a
 * file's unmapped columns into two distinct categories: fields that are
 * never imported by design (e.g. a computed field, explicitly mapped to
 * "-1") versus columns the strategy simply doesn't recognize at all - the
 * latter being the one actually worth a user's attention.
 *
 * @see \Drupal\mukurtu_import\Form\ImportFileSummaryForm::getMappedFieldsMessage()
 */
class ImportFileSummaryMessageTest extends MukurtuImportTestBase {

  /**
   * Builds a form instance with a given mapping registered for a CSV file
   * with the given headers, and returns the resulting summary message.
   */
  private function getMessageFor(array $headers, array $mapping): string {
    $data = [$headers, array_fill(0, count($headers), 'x')];
    $file = $this->createCsvFile($data);

    $strategy = MukurtuImportStrategy::create(['uid' => $this->currentUser->id()]);
    $strategy->setTargetEntityTypeId('node');
    $strategy->setTargetBundle('protocol_aware_content');
    $strategy->setMapping($mapping);

    $form = \Drupal\mukurtu_import\Form\ImportFileSummaryForm::create($this->container);
    $form->setImportConfig($file->id(), $strategy);

    $ref = new \ReflectionMethod($form, 'getMappedFieldsMessage');
    $ref->setAccessible(TRUE);
    return (string) $ref->invoke($form, $file->id());
  }

  /**
   * When every column either maps to a real target or is explicitly
   * ignored, the message reads as a clean full match - no mention of
   * "ignored" at all, and the always-ignored columns aren't counted
   * against the total.
   */
  public function testCleanMatchWithDesignIgnoredColumns(): void {
    $message = $this->getMessageFor(
      ['Title', 'Default translation'],
      [
        ['source' => 'Title', 'target' => 'title'],
        ['source' => 'Default translation', 'target' => '-1'],
      ]
    );

    $this->assertSame('1 of 1 importable fields mapped (1 field is never imported)', $message);
  }

  /**
   * A column with no mapping entry at all is called out distinctly as
   * "not recognized" rather than lumped in with the by-design-ignored
   * ones.
   */
  public function testUnrecognizedColumnCalledOutSeparately(): void {
    $message = $this->getMessageFor(
      ['Title', 'Default translation', 'Some Unknown Column'],
      [
        ['source' => 'Title', 'target' => 'title'],
        ['source' => 'Default translation', 'target' => '-1'],
      ]
    );

    $this->assertSame('1 of 2 importable fields mapped (1 field is never imported; 1 column is not recognized)', $message);
  }

  /**
   * With no by-design-ignored columns and no unrecognized ones, the
   * message has no parenthetical at all.
   */
  public function testFullyCleanMatchHasNoParenthetical(): void {
    $message = $this->getMessageFor(
      ['Title'],
      [['source' => 'Title', 'target' => 'title']]
    );

    $this->assertSame('1 of 1 importable fields mapped', $message);
  }

  /**
   * Multiple unrecognized columns pluralize correctly.
   */
  public function testMultipleUnrecognizedColumnsPluralize(): void {
    $message = $this->getMessageFor(
      ['Title', 'Unknown A', 'Unknown B'],
      [['source' => 'Title', 'target' => 'title']]
    );

    $this->assertSame('1 of 3 importable fields mapped (2 columns are not recognized)', $message);
  }

}
