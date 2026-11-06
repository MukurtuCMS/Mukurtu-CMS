<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_taxonomy\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Render\Element;
use Drupal\KernelTests\KernelTestBase;
use Drupal\og\Og;
use Drupal\taxonomy\Entity\Vocabulary;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that the vocabulary form doesn't offer Organic Groups settings.
 *
 * @see \Drupal\mukurtu_taxonomy\Hook\VocabularyFormHooks
 */
#[Group('mukurtu_taxonomy')]
class VocabularyOrganicGroupsSectionTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'mukurtu_taxonomy',
    'og',
    'og_ui',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'og']);
  }

  /**
   * Gets a vocabulary's built settings form.
   */
  protected function buildForm(Vocabulary $vocabulary): array {
    return $this->container->get('entity.form_builder')->getForm($vocabulary);
  }

  /**
   * Add and edit forms both hide the section, and the form still has it.
   */
  public function testSectionIsHidden(): void {
    $new = Vocabulary::create(['vid' => 'new_one', 'name' => 'New']);
    $form = $this->buildForm($new);
    $this->assertArrayHasKey('og', $form, 'og_ui adds the section in this test, so hiding it proves something.');
    $this->assertFalse(Element::isVisibleElement($form['og']));

    $existing = Vocabulary::create(['vid' => 'existing', 'name' => 'Existing']);
    $existing->save();
    $this->assertFalse(Element::isVisibleElement($this->buildForm($existing)['og']));
  }

  /**
   * Saving the form doesn't change a vocabulary's group settings.
   */
  public function testSavingKeepsGroupSettings(): void {
    $vocabulary = Vocabulary::create(['vid' => 'grouped', 'name' => 'Grouped']);
    $vocabulary->save();
    Og::groupTypeManager()->addGroup('taxonomy_term', 'grouped');

    $form_object = $this->container->get('entity_type.manager')->getFormObject('taxonomy_vocabulary', 'default');
    $form_object->setEntity($vocabulary);
    $form_state = (new FormState())->setValues(['name' => 'Renamed', 'vid' => 'grouped', 'op' => 'Save']);
    $this->container->get('form_builder')->submitForm($form_object, $form_state);

    $this->assertEmpty($form_state->getErrors());
    $this->assertSame('Renamed', Vocabulary::load('grouped')->label(), 'The form really saved.');
    $this->assertTrue(Og::isGroup('taxonomy_term', 'grouped'));
  }

}
