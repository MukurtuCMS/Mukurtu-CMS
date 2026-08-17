<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_local_contexts\Kernel;

use Drupal\Core\Entity\ContentEntityFormInterface;
use Drupal\Core\Form\FormState;
use Drupal\mukurtu_local_contexts\Hook\FormHooks;
use Drupal\node\Entity\Node;

/**
 * Tests the project/label overlap validation added by FormHooks.
 *
 * @see \Drupal\mukurtu_local_contexts\Hook\FormHooks::validateNoProjectLabelOverlap()
 * @group mukurtu_local_contexts
 */
class FormHooksProjectLabelOverlapTest extends LocalContextsTestBase {

  /**
   * Creates a new, unsaved test node.
   */
  protected function createTestNode(): Node {
    return Node::create([
      'type' => static::TEST_BUNDLE,
      'title' => $this->randomString(),
    ]);
  }

  /**
   * Runs the validator against an entity built from the given form values.
   */
  protected function validateEntity(Node $entity): FormState {
    $formObject = $this->createMock(ContentEntityFormInterface::class);
    $formObject->method('buildEntity')->willReturn($entity);

    $formState = new FormState();
    $formState->setFormObject($formObject);

    $form = [
      'field_local_contexts_projects' => [],
      'field_local_contexts_labels_and_notices' => [],
    ];

    FormHooks::validateNoProjectLabelOverlap($form, $formState);

    return $formState;
  }

  /**
   * Selecting a project and one of its own labels/notices is rejected.
   */
  public function testOverlapIsRejected(): void {
    $this->seedSiteProject('project_a', 'Project A');
    $this->seedLabel('label_a', 'project_a', 'Label A');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['project_a']);
    $entity->set('field_local_contexts_labels_and_notices', ['project_a:label_a:label']);

    $formState = $this->validateEntity($entity);

    $this->assertNotEmpty($formState->getErrors());
  }

  /**
   * A project and a label from a *different* project is not an overlap.
   */
  public function testNonOverlappingSelectionsPassValidation(): void {
    $this->seedSiteProject('project_a', 'Project A');
    $this->seedSiteProject('project_b', 'Project B');
    $this->seedLabel('label_b', 'project_b', 'Label B');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_projects', ['project_a']);
    $entity->set('field_local_contexts_labels_and_notices', ['project_b:label_b:label']);

    $formState = $this->validateEntity($entity);

    $this->assertEmpty($formState->getErrors());
  }

  /**
   * With no project selected, any label/notice selection is valid.
   */
  public function testNoProjectSelectedPassesValidation(): void {
    $this->seedSiteProject('project_a', 'Project A');
    $this->seedLabel('label_a', 'project_a', 'Label A');

    $entity = $this->createTestNode();
    $entity->set('field_local_contexts_labels_and_notices', ['project_a:label_a:label']);

    $formState = $this->validateEntity($entity);

    $this->assertEmpty($formState->getErrors());
  }

}
