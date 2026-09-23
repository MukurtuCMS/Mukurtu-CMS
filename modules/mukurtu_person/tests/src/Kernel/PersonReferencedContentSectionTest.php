<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_person\Kernel;

use Drupal\mukurtu_person\Hook\PersonPreprocessHooks;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Referenced Content section injected into Person full displays.
 *
 * @see \Drupal\mukurtu_person\Hook\PersonPreprocessHooks::preprocessNode()
 * @see \Drupal\mukurtu_core\Service\RelatedContentGrouper::build()
 */
#[Group('mukurtu_person')]
class PersonReferencedContentSectionTest extends PersonTestBase {

  /**
   * Run the preprocess hook over a node and return the resulting content array.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node being displayed.
   * @param array $content
   *   The 'content' variable as the view display would have built it.
   *
   * @return array
   *   The 'content' variable after the hook has run.
   */
  protected function preprocess($node, array $content): array {
    $variables = [
      'node' => $node,
      'view_mode' => 'full',
      'content' => $content,
    ];
    \Drupal::service(PersonPreprocessHooks::class)->preprocessNode($variables);
    return $variables['content'];
  }

  /**
   * A Person with no referenced content gets no section and no heading.
   */
  public function testEmptyPersonGetsNoSection(): void {
    $person = $this->buildPerson('Empty person');
    $person->save();

    // An empty computed field leaves the component in place but carrying only
    // cacheability, which is why the hook cannot treat presence as content.
    $content = $this->preprocess($person, ['field_all_related_content' => []]);

    $this->assertArrayNotHasKey('#theme', $content['field_all_related_content']);
    $this->assertArrayNotHasKey('#title', $content['field_all_related_content']);
  }

  /**
   * A Person with referenced content gets the labelled section.
   */
  public function testPopulatedPersonGetsLabelledSection(): void {
    $related = $this->buildPerson('Related person');
    $related->save();

    $person = $this->buildPerson('Referencing person');
    $person->set('field_related_content', [$related->id()]);
    $person->save();

    $content = $this->preprocess($person, ['field_all_related_content' => ['#weight' => 90]]);

    $this->assertSame('mukurtu_related_content_grouped', $content['field_all_related_content']['#theme']);
    $this->assertSame('Referenced Content', (string) $content['field_all_related_content']['#title']);
    $this->assertNotEmpty($content['field_all_related_content']['#items']);
  }

  /**
   * The section keeps the weight the view display assigned to the component.
   */
  public function testSectionKeepsConfiguredWeight(): void {
    $related = $this->buildPerson('Related person');
    $related->save();

    $person = $this->buildPerson('Referencing person');
    $person->set('field_related_content', [$related->id()]);
    $person->save();

    $content = $this->preprocess($person, ['field_all_related_content' => ['#weight' => 90]]);

    // Dropping the weight defaults the element to 0, which sorts it above the
    // media, text sections and map inside the primary fields group.
    $this->assertSame(90, $content['field_all_related_content']['#weight']);
  }

  /**
   * A field hidden on the view display is not injected back into the page.
   */
  public function testHiddenFieldIsNotInjected(): void {
    $related = $this->buildPerson('Related person');
    $related->save();

    $person = $this->buildPerson('Referencing person');
    $person->set('field_related_content', [$related->id()]);
    $person->save();

    $content = $this->preprocess($person, []);

    $this->assertArrayNotHasKey('field_all_related_content', $content);
  }

  /**
   * The shared theme hook renders nothing when it is handed no items.
   */
  public function testThemeHookRendersNothingWithoutItems(): void {
    $build = [
      '#theme' => 'mukurtu_related_content_grouped',
      '#items' => [],
      '#filters' => [],
      '#has_filters' => FALSE,
      '#title' => 'Referenced Content',
    ];

    $output = (string) \Drupal::service('renderer')->renderRoot($build);

    $this->assertStringNotContainsString('related-content__label', $output);
    $this->assertStringNotContainsString('related-content', $output);
  }

}
