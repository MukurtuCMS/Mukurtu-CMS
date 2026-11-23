<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\mukurtu_import\Form\CustomStrategyFromFileForm;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that each Target Field select on the mapping step has a label.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/2667
 */
#[CoversClass(CustomStrategyFromFileForm::class)]
#[Group('mukurtu_import')]
class MappingTargetLabelTest extends MukurtuImportTestBase {

  /**
   * Tests that each select is labeled with its row's column name.
   */
  public function testTargetSelectsNameTheirColumn(): void {
    $file = $this->createCsvFile([['Title', 'Summary'], ['An item', 'About it']]);
    $form = $this->container->get('form_builder')->getForm(CustomStrategyFromFileForm::class, $file);
    $this->render($form);

    foreach (['Title', 'Summary'] as $delta => $column) {
      $select = $this->xpath('//select[@name=:name]', [':name' => "mappings[$delta][target]"]);
      $this->assertCount(1, $select, "The select for $column is on the form.");
      $id = (string) $select[0]['id'];
      $label = $this->xpath('//label[@for=:id]', [':id' => $id]);
      $this->assertCount(1, $label, "The select for $column has a label.");
      $this->assertSame("Target field for $column", trim((string) $label[0]));
      $this->assertStringContainsString('visually-hidden', (string) $label[0]['class']);
    }
  }

}
