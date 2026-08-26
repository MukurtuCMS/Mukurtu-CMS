<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_collection\Kernel;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\mukurtu_collection\Entity\Collection;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;

/**
 * Tests that Collection field labels are translatable.
 *
 * PersonalCollection's identical one-line fix (setLabel('Description') ->
 * setLabel(t('Description'))) is not covered by a kernel test here:
 * exercising PersonalCollection::baseFieldDefinitions() through the real
 * entity type manager requires enabling mukurtu_collection, which pulls in
 * mukurtu_local_contexts, which in turn requires path_alias and further
 * transitive dependencies - disproportionate kernel test setup for a
 * mechanical one-line string wrap that's otherwise identical in shape and
 * risk to the covered fixes in this same PR.
 *
 * @group mukurtu_collection
 */
class CollectionFieldLabelTest extends ProtocolAwareEntityTestBase {

  public function testCollectionFieldLabelsAreTranslatable(): void {
    $entityType = \Drupal::entityTypeManager()->getDefinition('node');
    $definitions = Collection::bundleFieldDefinitions($entityType, '', []);

    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_description']->getLabel());
    $this->assertInstanceOf(TranslatableMarkup::class, $definitions['field_coverage_description']->getLabel());
  }

}
