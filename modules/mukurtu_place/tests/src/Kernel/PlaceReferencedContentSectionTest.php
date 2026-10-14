<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_place\Kernel;

use Drupal\mukurtu_place\Hook\PlacePreprocessHooks;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the Referenced Content section injected into Place full displays.
 *
 * @see \Drupal\mukurtu_place\Hook\PlacePreprocessHooks::preprocessNode()
 * @see \Drupal\mukurtu_core\Service\RelatedContentGrouper::build()
 */
#[Group('mukurtu_place')]
class PlaceReferencedContentSectionTest extends PlaceTestBase {

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
    \Drupal::service(PlacePreprocessHooks::class)->preprocessNode($variables);
    return $variables['content'];
  }

  /**
   * A Place with no referenced content gets no section and no heading.
   */
  public function testEmptyPlaceGetsNoSection(): void {
    $place = $this->buildPlace('Empty place');
    $place->save();

    // An empty computed field leaves the component in place but carrying only
    // cacheability, which is why the hook cannot treat presence as content.
    $content = $this->preprocess($place, ['field_all_related_content' => []]);

    $this->assertArrayNotHasKey('#theme', $content['field_all_related_content']);
    $this->assertArrayNotHasKey('#title', $content['field_all_related_content']);
  }

  /**
   * A Place with referenced content gets the labelled section.
   */
  public function testPopulatedPlaceGetsLabelledSection(): void {
    $related = $this->buildPlace('Related place');
    $related->save();

    $place = $this->buildPlace('Referencing place');
    $place->set('field_related_content', [$related->id()]);
    $place->save();

    $content = $this->preprocess($place, ['field_all_related_content' => ['#weight' => 90]]);

    $this->assertSame('mukurtu_related_content_grouped', $content['field_all_related_content']['#theme']);
    $this->assertSame('Referenced Content', (string) $content['field_all_related_content']['#title']);
    $this->assertNotEmpty($content['field_all_related_content']['#items']);
  }

  /**
   * The section keeps the weight the view display assigned to the component.
   */
  public function testSectionKeepsConfiguredWeight(): void {
    $related = $this->buildPlace('Related place');
    $related->save();

    $place = $this->buildPlace('Referencing place');
    $place->set('field_related_content', [$related->id()]);
    $place->save();

    $content = $this->preprocess($place, ['field_all_related_content' => ['#weight' => 90]]);

    // Dropping the weight defaults the element to 0, which sorts it above the
    // media, text sections and map inside the primary fields group.
    $this->assertSame(90, $content['field_all_related_content']['#weight']);
  }

  /**
   * A field hidden on the view display is not injected back into the page.
   */
  public function testHiddenFieldIsNotInjected(): void {
    $related = $this->buildPlace('Related place');
    $related->save();

    $place = $this->buildPlace('Referencing place');
    $place->set('field_related_content', [$related->id()]);
    $place->save();

    $content = $this->preprocess($place, []);

    $this->assertArrayNotHasKey('field_all_related_content', $content);
  }

  /**
   * Referenced content the user cannot view leaves no empty section behind.
   */
  public function testUnviewableReferencedContentGetsNoSection(): void {
    // A strict protocol the viewer is not a member of, so its content is
    // invisible to them.
    $closedProtocol = Protocol::create([
      'name' => 'Closed Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $closedProtocol->save();

    $related = $this->buildPlace('Protocol-gated related place');
    $related->setProtocols([$closedProtocol]);
    $related->save();

    $place = $this->buildPlace('Referencing place');
    $place->set('field_related_content', [$related->id()]);
    $place->save();

    // The reference survives on the computed field, so the section can only be
    // suppressed by the grouper's per-item access check.
    $this->assertNotEmpty($place->get('field_all_related_content')->referencedEntities());

    // $this->currentUser is uid 1, which bypasses access entirely, so the
    // check has to run as somebody outside the protocol.
    $viewer = User::create(['name' => $this->randomMachineName()]);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);
    $this->assertFalse($related->access('view', $viewer));

    $content = $this->preprocess($place, ['field_all_related_content' => ['#weight' => 90]]);

    $this->assertArrayNotHasKey('#theme', $content['field_all_related_content']);
    $this->assertArrayNotHasKey('#title', $content['field_all_related_content']);
  }

}
