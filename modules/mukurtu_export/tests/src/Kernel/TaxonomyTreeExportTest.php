<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_export\Form\TaxonomyExportStartForm;
use Drupal\mukurtu_export\Plugin\MukurtuExporter\CSV;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests exporting nested taxonomy terms (#2520).
 *
 * @see \Drupal\mukurtu_export\Form\TaxonomyExportStartForm
 * @see \Drupal\mukurtu_export\Plugin\MukurtuExporter\CSV::batchExport()
 */
#[Group('mukurtu_export')]
class TaxonomyTreeExportTest extends CsvExportFieldTestBase {

  /**
   * Terms are exported parents first, in the order the vocabulary shows them.
   */
  public function testExportsInTreeOrder(): void {
    Vocabulary::create(['vid' => 'location', 'name' => 'Location'])->save();
    // Created child first, so ID order would put it before its parent.
    $spokane = Term::create(['vid' => 'location', 'name' => 'Spokane']);
    $spokane->save();
    $washington = Term::create(['vid' => 'location', 'name' => 'Washington']);
    $washington->save();
    $spokane->set('parent', $washington->id())->save();
    $alaska = Term::create(['vid' => 'location', 'name' => 'Alaska']);
    $alaska->save();

    $form_state = (new FormState())->setValues([
      'vocabularies' => ['location' => 'location'],
      'op' => 'Configure Export',
    ]);
    $this->container->get('form_builder')->submitForm(TaxonomyExportStartForm::class, $form_state);

    $this->assertSame([], array_map('strval', $form_state->getErrors()));
    $items = $this->container->get('tempstore.private')->get('mukurtu_import')->get('ad_hoc_items');
    $this->assertSame(
      [(int) $alaska->id(), (int) $washington->id(), (int) $spokane->id()],
      array_values($items['taxonomy_term'])
    );
  }

  /**
   * An entity queued again after it was exported doesn't stall the batch.
   *
   * With the "entity" reference options, exporting a child term queues its
   * parent again. Left in the queue, it was picked up on every pass and the
   * batch never finished.
   */
  public function testAlreadyExportedEntityIsDequeued(): void {
    $context = [
      'sandbox' => [
        'config' => $this->export_config,
        'batch' => ['entity_type_id' => 'taxonomy_term', 'entities' => ['5']],
      ],
      'results' => [
        'entities' => ['taxonomy_term' => ['5' => '5']],
        'exported_entities' => ['taxonomy_term' => ['5' => TRUE]],
      ],
    ];

    CSV::batchExport($context);

    $this->assertSame([], $context['results']['entities']['taxonomy_term'] ?? []);
  }

}
