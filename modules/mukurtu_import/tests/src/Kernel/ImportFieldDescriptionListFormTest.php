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

    $optional_options = $form['table_optional']['#options'];
    $this->assertArrayHasKey('communities', $optional_options);
    $this->assertArrayHasKey('protocols', $optional_options);
    $this->assertStringContainsString('CommunityName>role', (string) $optional_options['communities']['format']);
    $this->assertStringContainsString('ProtocolName>role', (string) $optional_options['protocols']['format']);
  }

  /**
   * Communities/protocols are never required, even though they have no
   * FieldDefinitionInterface to check isRequired() on. The username is a
   * required base field, so the required/optional split still applies to
   * the user entity type like any other.
   */
  public function testUserFormatDescriptionPageSeparatesRequiredFromOptional(): void {
    $form_object = \Drupal::classResolver(ImportFieldDescriptionListForm::class);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, 'user', 'user');

    $required_options = $form['table_required']['#options'];
    $optional_options = $form['table_optional']['#options'];

    $this->assertArrayHasKey('name', $required_options);
    $this->assertArrayNotHasKey('communities', $required_options);
    $this->assertArrayNotHasKey('protocols', $required_options);
    $this->assertEmpty(array_intersect_key($required_options, $optional_options));
  }

  /**
   * The Roles field's description clarifies that "Authenticated user" is
   * always granted automatically, since Drupal core computes it dynamically
   * for every account (User::getRoles()) regardless of what's mapped here.
   */
  public function testRolesFieldDescriptionExplainsAuthenticatedIsAutomatic(): void {
    $form_object = \Drupal::classResolver(ImportFieldDescriptionListForm::class);
    $form_state = new FormState();
    $form = $form_object->buildForm([], $form_state, 'user', 'user');

    $optional_options = $form['table_optional']['#options'];
    $this->assertArrayHasKey('roles', $optional_options);
    $this->assertStringContainsString('automatically receives the "Authenticated user" role', (string) $optional_options['roles']['description']);
  }

}
