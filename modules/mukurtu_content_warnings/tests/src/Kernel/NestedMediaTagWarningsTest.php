<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_content_warnings\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_content_warnings\Form\MukurtuContentWarningsSettingsForm;
use Drupal\mukurtu_content_warnings\Hook\MukurtuMediaContentWarnings;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that a warning on a media tag reaches the terms nested under it.
 *
 * @see \Drupal\mukurtu_content_warnings\Hook\MukurtuMediaContentWarnings
 */
#[Group('mukurtu_content_warnings')]
class NestedMediaTagWarningsTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'entity_reference_revisions',
    'field',
    'file',
    'filter',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_content_warnings',
    'mukurtu_core',
    'mukurtu_protocol',
    'node',
    'og',
    'options',
    'paragraphs',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * Media tags: Ceremony > Funeral > Burial, and Landscape.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $terms;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('taxonomy_term');
    $this->installConfig(['filter', 'system']);
    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media Tag'])->save();

    $ceremony = $this->createTerm('Ceremony');
    $funeral = $this->createTerm('Funeral', $ceremony);
    $burial = $this->createTerm('Burial', $funeral);
    $landscape = $this->createTerm('Landscape');
    $this->terms = compact('ceremony', 'funeral', 'burial', 'landscape');
  }

  /**
   * Creates a media tag.
   */
  protected function createTerm(string $name, ?TermInterface $parent = NULL): TermInterface {
    $term = Term::create(['vid' => 'media_tag', 'name' => $name, 'parent' => $parent?->id() ?? 0]);
    $term->save();
    return $term;
  }

  /**
   * Saves tag warnings as [term key, warning text] pairs.
   */
  protected function setWarnings(array $warnings): void {
    $this->config('mukurtu_content_warnings.settings')
      ->set('taxonomy_warnings', array_map(fn ($warning) => ['term' => (int) $this->terms[$warning[0]]->id(), 'warning' => $warning[1]], $warnings))
      ->save();
  }

  /**
   * Builds the warnings for media with some tags, as a page would.
   *
   * @return array
   *   The media_content_warnings render array.
   */
  protected function buildWarnings(array $tags): array {
    $field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $field->method('referencedEntities')->willReturn($tags);
    $media = $this->createMockForIntersectionOfInterfaces([ContentEntityInterface::class, PeopleInterface::class]);
    $media->method('get')->willReturn($field);
    $media->method('getPeopleTerms')->willReturn([]);
    $media->method('hasField')->willReturn(FALSE);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheTags')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    $display = $this->createMock(EntityViewDisplayInterface::class);
    $display->method('getMode')->willReturn('full');

    $hook = new MukurtuMediaContentWarnings(
      $this->container->get('config.factory'),
      $this->container->get('entity_type.manager'),
      $this->container->get('router.admin_context'),
    );
    $build = ['#view_mode' => 'full'];
    $hook->mediaViewAlter($build, $media, $display);
    return $build['media_content_warnings'];
  }

  /**
   * Gets the warning texts in a build.
   */
  protected function warningTexts(array $build): array {
    return array_column($build['#warnings'] ?? [], '#warning');
  }

  /**
   * A warning shows on media tagged with a nested term, at any depth.
   */
  public function testWarningAppliesToNestedTerms(): void {
    $this->setWarnings([['ceremony', 'Shows a ceremony.']]);

    $this->assertSame(['Shows a ceremony.'], $this->warningTexts($this->buildWarnings([$this->terms['ceremony']])));
    $this->assertSame(['Shows a ceremony.'], $this->warningTexts($this->buildWarnings([$this->terms['funeral']])));
    $this->assertSame(['Shows a ceremony.'], $this->warningTexts($this->buildWarnings([$this->terms['burial']])));
    $this->assertSame([], $this->warningTexts($this->buildWarnings([$this->terms['landscape']])));
  }

  /**
   * A warning doesn't reach up to the terms above its term.
   */
  public function testWarningDoesNotApplyToParentTerms(): void {
    $this->setWarnings([['funeral', 'Shows a funeral.']]);

    $this->assertSame([], $this->warningTexts($this->buildWarnings([$this->terms['ceremony']])));
    $this->assertSame(['Shows a funeral.'], $this->warningTexts($this->buildWarnings([$this->terms['burial']])));
  }

  /**
   * A term added under a warned term is covered with no settings change.
   */
  public function testNewNestedTermIsCovered(): void {
    $this->setWarnings([['ceremony', 'Shows a ceremony.']]);
    $new = $this->createTerm('Wake', $this->terms['funeral']);

    $this->assertSame(['Shows a ceremony.'], $this->warningTexts($this->buildWarnings([$new])));
  }

  /**
   * Different warnings all show, and identical text shows once.
   */
  public function testWarningsCombine(): void {
    $this->setWarnings([
      ['ceremony', 'Shows a ceremony.'],
      ['funeral', 'Shows a funeral.'],
      ['burial', 'Shows a ceremony.'],
    ]);

    $this->assertSame(['Shows a ceremony.', 'Shows a funeral.'], $this->warningTexts($this->buildWarnings([$this->terms['burial']])));
  }

  /**
   * Moving a tag or its ancestors updates a media item with no warning yet.
   */
  public function testRenderDependsOnTheWholeBranch(): void {
    $this->setWarnings([['landscape', 'Shows a landscape.']]);
    $tags = $this->buildWarnings([$this->terms['burial']])['#cache']['tags'];

    foreach (['ceremony', 'funeral', 'burial'] as $key) {
      $this->assertContains('taxonomy_term:' . $this->terms[$key]->id(), $tags);
    }
  }

  /**
   * The settings form lists tags with their paths, in tree order.
   */
  public function testSettingsFormListsPaths(): void {
    $form = \Drupal::formBuilder()->getForm(MukurtuContentWarningsSettingsForm::class);
    // The form adds an empty option when it builds the select.
    $options = array_diff_key($form['taxonomy_warnings'][0]['term']['#options'], ['' => '']);

    $this->assertSame(
      ['Ceremony', 'Ceremony › Funeral', 'Ceremony › Funeral › Burial', 'Landscape'],
      array_values($options),
    );
  }

}
