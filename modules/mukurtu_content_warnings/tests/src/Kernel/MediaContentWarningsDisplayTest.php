<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_content_warnings\Kernel;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_content_warnings\Hook\MukurtuMediaContentWarnings;
use Drupal\mukurtu_core\Entity\PeopleInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Tests how several content warnings on one media item are built and shown.
 *
 * @see https://github.com/MukurtuCMS/Mukurtu-CMS/issues/1301
 */
class MediaContentWarningsDisplayTest extends KernelTestBase {

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
    'language',
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
   * People terms keyed by name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $people = [];

  /**
   * Media tag terms keyed by name.
   *
   * @var \Drupal\taxonomy\TermInterface[]
   */
  protected array $tags = [];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['system', 'language']);

    Vocabulary::create(['vid' => 'people', 'name' => 'People'])->save();
    Vocabulary::create(['vid' => 'media_tag', 'name' => 'Media tag'])->save();
    NodeType::create(['type' => 'person', 'name' => 'Person'])->save();

    FieldStorageConfig::create([
      'field_name' => 'field_deceased',
      'entity_type' => 'node',
      'type' => 'boolean',
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_deceased',
      'entity_type' => 'node',
      'bundle' => 'person',
    ])->save();
    FieldStorageConfig::create([
      'field_name' => 'field_other_names',
      'entity_type' => 'node',
      'type' => 'entity_reference',
      'settings' => ['target_type' => 'taxonomy_term'],
      'cardinality' => -1,
    ])->save();
    FieldConfig::create([
      'field_name' => 'field_other_names',
      'entity_type' => 'node',
      'bundle' => 'person',
    ])->save();

    foreach (['Ada Example', 'Ben & Cy Example'] as $name) {
      $term = Term::create(['vid' => 'people', 'name' => $name]);
      $term->save();
      $this->people[$name] = $term;
      Node::create([
        'type' => 'person',
        'title' => $name,
        'field_deceased' => TRUE,
        'field_other_names' => [['target_id' => $term->id()]],
      ])->save();
    }
    foreach (['Ceremony', 'Funeral', 'Burial'] as $name) {
      $term = Term::create(['vid' => 'media_tag', 'name' => $name]);
      $term->save();
      $this->tags[$name] = $term;
    }

    $this->config('mukurtu_content_warnings.settings')
      ->set('people_warnings', [
        'enabled' => TRUE,
        'warning_single' => '[name] is deceased.',
        'warning_multiple' => 'These people are deceased: [names]',
      ])
      ->set('taxonomy_warnings', [
        ['term' => (int) $this->tags['Ceremony']->id(), 'warning' => 'Shows a ceremony.'],
        ['term' => (int) $this->tags['Funeral']->id(), 'warning' => 'Shows burial practices.'],
        ['term' => (int) $this->tags['Burial']->id(), 'warning' => 'Shows burial practices.'],
      ])
      ->save();
  }

  /**
   * Stacked warnings keep their order, drop duplicates, and separate names.
   */
  public function testStackedWarnings(): void {
    $media = $this->mockMedia(array_values($this->people), array_values($this->tags));
    $this->assertSame([
      'These people are deceased: Ada Example, Ben & Cy Example',
      'Shows a ceremony.',
      'Shows burial practices.',
    ], $this->warningTexts($media));
  }

  /**
   * A single deceased person uses the single-person text.
   */
  public function testSinglePerson(): void {
    $media = $this->mockMedia([$this->people['Ben & Cy Example']], []);
    $this->assertSame(['Ben & Cy Example is deceased.'], $this->warningTexts($media));
  }

  /**
   * Person names come from the translation for the current content language.
   */
  public function testPersonNameIsTranslated(): void {
    ConfigurableLanguage::createFromLangcode('es')->save();
    $person = Node::load(1);
    $person->addTranslation('es', ['title' => 'Ada Ejemplo'])->save();
    $this->config('system.site')->set('default_langcode', 'es')->save();
    $this->container->get('language_manager')->reset();

    $media = $this->mockMedia([$this->people['Ada Example']], []);
    $this->assertSame(['Ada Ejemplo is deceased.'], $this->warningTexts($media));
  }

  /**
   * Several warnings render under a plural title, as a list that describes a
   * real button.
   */
  public function testMarkupForSeveralWarnings(): void {
    $build = [
      '#theme' => 'mukurtu_content_warnings',
      '#media' => $this->tags['Ceremony'],
      '#warnings' => [
        ['#theme' => 'mukurtu_content_warning', '#warning' => 'First warning'],
        ['#theme' => 'mukurtu_content_warning', '#warning' => 'Second warning'],
      ],
    ];
    $this->render($build);
    $id = 'mukurtu-content-warnings-' . $this->tags['Ceremony']->id();

    $overlay = $this->cssSelect('.mukurtu-content-warnings')[0];
    $this->assertEmpty((string) $overlay['role'], 'The overlay is not itself a button.');
    $this->assertEmpty((string) $overlay['aria-label'], 'The overlay has no label that would hide the warnings.');
    $this->assertSame('-1', (string) $overlay['tabindex'], 'The overlay is never a Tab stop, even when it scrolls.');

    $items = $this->cssSelect("ul#{$id}[role=list] > li");
    $this->assertCount(2, $items);
    $this->assertStringContainsString('First warning', $items[0]->asXML());
    $this->assertStringContainsString('Second warning', $items[1]->asXML());

    $button = $this->cssSelect('.mukurtu-content-warnings > .mukurtu-content-warnings__actions > button.mukurtu-content-warnings__dismiss');
    $this->assertCount(1, $button);
    $this->assertSame('button', (string) $button[0]['type']);
    $this->assertSame("$id-title $id", (string) $button[0]['aria-describedby']);
    $this->assertSame('Show media', trim((string) $button[0]));
    $this->assertSame('Media content warnings', trim((string) $this->cssSelect("p#$id-title")[0]));
    $this->assertSame('Read warnings', trim((string) $this->cssSelect('p.mukurtu-content-warnings__card-hint')[0]));
  }

  /**
   * One warning renders without a list.
   */
  public function testMarkupForOneWarning(): void {
    $build = [
      '#theme' => 'mukurtu_content_warnings',
      '#media' => $this->tags['Ceremony'],
      '#warnings' => [
        ['#theme' => 'mukurtu_content_warning', '#warning' => 'Only warning'],
      ],
    ];
    $this->render($build);
    $id = 'mukurtu-content-warnings-' . $this->tags['Ceremony']->id();

    $this->assertEmpty($this->cssSelect('.mukurtu-content-warnings ul'));
    $this->assertStringContainsString('Only warning', $this->cssSelect("div#{$id}")[0]->asXML());
    $this->assertSame("$id-title $id", (string) $this->cssSelect('button.mukurtu-content-warnings__dismiss')[0]['aria-describedby']);
    $this->assertSame('Media content warning', trim((string) $this->cssSelect("p#$id-title")[0]));
  }

  /**
   * Builds a stand-in media entity with the given people and media tags.
   *
   * @param \Drupal\taxonomy\TermInterface[] $people
   *   People terms.
   * @param \Drupal\taxonomy\TermInterface[] $tags
   *   Media tag terms.
   */
  protected function mockMedia(array $people, array $tags): PeopleInterface {
    $tag_field = $this->createMock(EntityReferenceFieldItemListInterface::class);
    $tag_field->method('referencedEntities')->willReturn($tags);
    $media = $this->createMockForIntersectionOfInterfaces([PeopleInterface::class, ContentEntityInterface::class]);
    $media->method('getPeopleTerms')->willReturn($people);
    $media->method('hasField')->willReturn(FALSE);
    $media->method('get')->with('field_media_tags')->willReturn($tag_field);
    $media->method('getCacheContexts')->willReturn([]);
    $media->method('getCacheTags')->willReturn([]);
    $media->method('getCacheMaxAge')->willReturn(-1);
    return $media;
  }

  /**
   * Returns the warning texts the hook builds for a media entity.
   */
  protected function warningTexts(PeopleInterface $media): array {
    $hooks = $this->container->get(MukurtuMediaContentWarnings::class);
    $build = (fn() => $this->buildMediaContentWarnings($media))->call($hooks);
    return array_map(fn(array $warning) => (string) $warning['#warning'], $build['#warnings'] ?? []);
  }

}
