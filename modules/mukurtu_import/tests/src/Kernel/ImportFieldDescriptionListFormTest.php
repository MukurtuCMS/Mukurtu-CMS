<?php

declare(strict_types = 1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm;

/**
 * Tests the user import format-description page.
 *
 * Regression coverage: buildTargetOptions() injects two virtual, non-field
 * target keys (communities, protocols) for the user entity type. Before this
 * fix, ImportFieldDescriptionListForm::buildForm() unconditionally looked up
 * a field definition for every target key and handed it to the field
 * process plugin manager, which fatally errored ("Call to a member function
 * getType() on null") for these two synthetic keys, since they have no
 * matching field definition. That made /admin/import/format/user/user
 * inaccessible, leaving the Communities/Protocols import format entirely
 * undocumented.
 *
 * @see \Drupal\mukurtu_import\Form\ImportFieldDescriptionListForm
 */
class ImportFieldDescriptionListFormTest extends MukurtuImportTestBase {

  /**
   * Test that the user/user format-description page renders without a fatal
   * and documents the Communities/Protocols import format.
   */
  public function testUserFormatDescriptionPageDoesNotCrash(): void {
    $form_object = \Drupal::classResolver(ImportFieldDescriptionListForm::class);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, 'user', 'user');

    $options = $form['table']['#options'];
    $this->assertArrayHasKey('communities', $options);
    $this->assertArrayHasKey('protocols', $options);
    $this->assertStringContainsString('CommunityName:role', (string) $options['communities']['format']);
    $this->assertStringContainsString('ProtocolName:role', (string) $options['protocols']['format']);
  }

}
