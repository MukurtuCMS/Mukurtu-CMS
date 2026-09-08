<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_core\Entity\FormattedTextWithTitle;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that FormattedTextWithTitle's field label/description are
 * translatable. Every setLabel()/setDescription() call in this file was
 * previously a plain string - 100% untranslated.
 */
#[Group('mukurtu_core')]
class FormattedTextWithTitleFieldLabelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'entity_reference_revisions',
    'paragraphs',
  ];

  public function testTitleAndBodyFieldsAreTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('paragraph');
    $definitions = FormattedTextWithTitle::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_title']->getLabel());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_title']->getDescription());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_body']->getLabel());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_body']->getDescription());
  }

}
