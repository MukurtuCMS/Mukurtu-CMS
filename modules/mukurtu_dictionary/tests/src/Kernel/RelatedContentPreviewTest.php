<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Form\FormState;
use Drupal\mukurtu_core\Form\MukurtuSettingsForm;
use Drupal\mukurtu_core\Hook\RelatedContentPreviewHooks;
use Drupal\mukurtu_dictionary\Entity\DictionaryWord;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the related content preview in a dictionary word's sidebar.
 *
 * @see \Drupal\mukurtu_core\Hook\RelatedContentPreviewHooks
 */
#[Group('mukurtu_dictionary')]
class RelatedContentPreviewTest extends DictionaryTestBase {

  /**
   * The word whose page is being built.
   */
  protected DictionaryWord $word;

  /**
   * Related words the viewer can see.
   *
   * @var \Drupal\mukurtu_dictionary\Entity\DictionaryWord[]
   */
  protected array $visible = [];

  /**
   * A related word in a strict protocol the viewer is not a member of.
   */
  protected DictionaryWord $restricted;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $strict = Protocol::create([
      'name' => 'Strict Protocol',
      'field_communities' => [$this->community->id()],
      'field_access_mode' => 'strict',
    ]);
    $strict->save();
    $strict->addMember($this->currentUser, ['protocol_steward']);

    for ($i = 1; $i <= 6; $i++) {
      $related = $this->buildDictionaryWord("related $i");
      $related->save();
      $this->visible[] = $related;
    }
    $this->restricted = $this->buildDictionaryWord('restricted');
    $this->restricted->setProtocols([$strict]);
    $this->restricted->save();

    $this->word = $this->createWordRelatedTo([...$this->visible, $this->restricted]);

    // View the page as someone outside the strict protocol.
    $viewer = User::create(['name' => 'viewer']);
    $viewer->save();
    $this->container->get('current_user')->setAccount($viewer);
  }

  /**
   * Creates a word whose related content field lists the given nodes.
   */
  private function createWordRelatedTo(array $related): DictionaryWord {
    $word = $this->buildDictionaryWord('aloha');
    $word->set('field_related_content', array_map(fn($node) => ['target_id' => $node->id()], $related));
    $word->save();
    return Node::load($word->id());
  }

  /**
   * Sets the dictionary word preview mode.
   */
  private function setMode(string $mode): void {
    $this->config('mukurtu.settings')->set('related_content_preview.dictionary_word', $mode)->save();
  }

  /**
   * Builds a full view of a word the way field_group leaves it, then alters it.
   */
  private function alteredBuild(?DictionaryWord $word = NULL, bool $with_group = TRUE, string $view_mode = 'full'): array {
    $build = [
      '#view_mode' => $view_mode,
      'field_dictionary_word_language' => ['#markup' => 'Language', '#weight' => 34],
      'field_keywords' => ['#markup' => 'Keywords', '#weight' => 36],
      'field_all_related_content' => ['#markup' => 'Related content', '#weight' => 39],
    ];
    if ($with_group) {
      $build['#fieldgroups']['group_metadata'] = (object) [
        'children' => ['field_dictionary_word_language', 'field_keywords'],
      ];
      $build['#group_children'] = [
        'field_dictionary_word_language' => 'group_metadata',
        'field_keywords' => 'group_metadata',
      ];
    }
    RelatedContentPreviewHooks::create($this->container)
      ->entityViewAlter($build, $word ?? $this->word, $this->createMock(EntityViewDisplayInterface::class));
    return $build;
  }

  /**
   * Renders the preview element.
   */
  private function renderPreview(array $build): string {
    return (string) $this->container->get('renderer')->renderInIsolation($build['related_content_preview']);
  }

  /**
   * Nothing is added until a site turns the preview on.
   */
  public function testOffByDefault(): void {
    $build = $this->alteredBuild();
    $this->assertArrayNotHasKey('related_content_preview', $build);
    $this->assertArrayNotHasKey('#attributes', $build['field_all_related_content']);
    // Turning it on later must reach pages already cached.
    $this->assertContains('config:mukurtu.settings', $build['#cache']['tags']);
  }

  /**
   * Only the full page gets a preview.
   */
  public function testOtherViewModesAreLeftAlone(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $this->assertArrayNotHasKey('related_content_preview', $this->alteredBuild(view_mode: 'teaser'));
  }

  /**
   * The jump link counts only items the viewer can see.
   */
  public function testJumpLink(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild();
    $target = 'related-content-' . $this->word->id();

    $preview = $build['related_content_preview'];
    $this->assertSame(6, $preview['#count']);
    $this->assertSame($target, $preview['#target']);
    $this->assertSame([], $preview['#items']);
    $this->assertContains('node_list', $build['#cache']['tags']);

    $html = $this->renderPreview($build);
    $this->assertStringContainsString('href="#' . $target . '"', $html);
    $this->assertStringContainsString('6 related items', $html);
    $this->assertStringNotContainsString('<details', $html);
  }

  /**
   * One related item reads in the singular.
   */
  public function testSingularCount(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild($this->createWordRelatedTo([$this->visible[0]]));
    $this->assertStringContainsString('1 related item<', str_replace(["\n", ' <'], ['', '<'], $this->renderPreview($build)));
  }

  /**
   * The preview follows the language field in the sidebar group.
   */
  public function testPlacedAfterLanguage(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild();

    $this->assertSame(34.5, $build['related_content_preview']['#weight']);
    $this->assertSame('group_metadata', $build['#group_children']['related_content_preview']);
    $this->assertContains('related_content_preview', $build['#fieldgroups']['group_metadata']->children);
  }

  /**
   * Without the sidebar group, the template prints the preview itself.
   */
  public function testWithoutGroup(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild(with_group: FALSE);

    $this->assertArrayHasKey('related_content_preview', $build);
    $this->assertArrayNotHasKey('#group_children', $build);
  }

  /**
   * The thumbnail list shows up to 5 visible items and links to the rest.
   */
  public function testThumbnails(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_THUMBNAILS);
    $build = $this->alteredBuild();

    $preview = $build['related_content_preview'];
    $this->assertSame(6, $preview['#count']);
    $this->assertSame(
      ['related 1', 'related 2', 'related 3', 'related 4', 'related 5'],
      array_column($preview['#items'], 'title'),
    );

    $html = $this->renderPreview($build);
    $this->assertStringContainsString('<details', $html);
    $this->assertSame(5, substr_count($html, 'related-content-preview__item'));
    $this->assertStringContainsString('View all 6 related items', $html);
    $this->assertStringNotContainsString('restricted', $html);
  }

  /**
   * No "View all" link when every item is already listed.
   */
  public function testThumbnailsWithoutViewAll(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_THUMBNAILS);
    $build = $this->alteredBuild($this->createWordRelatedTo(array_slice($this->visible, 0, 2)));

    $html = $this->renderPreview($build);
    $this->assertSame(2, substr_count($html, 'related-content-preview__item'));
    $this->assertStringNotContainsString('View all', $html);
  }

  /**
   * A word whose related items are all hidden from the viewer gets nothing.
   */
  public function testNoVisibleItems(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild($this->createWordRelatedTo([$this->restricted]));
    $this->assertArrayNotHasKey('related_content_preview', $build);
  }

  /**
   * The related content section takes the id the preview links to.
   */
  public function testSectionTarget(): void {
    $this->setMode(RelatedContentPreviewHooks::MODE_JUMP_LINK);
    $build = $this->alteredBuild();
    $this->assertSame(
      ['id' => 'related-content-' . $this->word->id(), 'tabindex' => '-1'],
      $build['field_all_related_content']['#attributes'],
    );

    // A field rendered with those attributes carries them on its wrapper.
    $field = $this->word->get('field_all_related_content')->view(['label' => 'hidden']);
    $field['#attributes'] = $build['field_all_related_content']['#attributes'];
    $html = (string) $this->container->get('renderer')->renderInIsolation($field);
    $this->assertStringContainsString('id="related-content-' . $this->word->id() . '"', $html);
  }

  /**
   * The settings form saves each content type's choice.
   */
  public function testSettingsForm(): void {
    $form_state = (new FormState())->setValues([
      'mukurtu_related_content_display' => 'computed',
      'related_content_preview' => [
        'dictionary_word' => RelatedContentPreviewHooks::MODE_THUMBNAILS,
        'word_list' => RelatedContentPreviewHooks::MODE_OFF,
      ],
      'op' => 'Save configuration',
    ]);
    $this->container->get('form_builder')->submitForm(MukurtuSettingsForm::class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $config = $this->config('mukurtu.settings');
    $this->assertSame(RelatedContentPreviewHooks::MODE_THUMBNAILS, RelatedContentPreviewHooks::getMode($config, 'dictionary_word'));
    $this->assertSame(RelatedContentPreviewHooks::MODE_OFF, RelatedContentPreviewHooks::getMode($config, 'word_list'));
  }

  /**
   * The schema accepts the three modes and nothing else.
   */
  public function testSchemaRejectsUnknownMode(): void {
    $typed_config = $this->container->get('config.typed');
    $valid = $typed_config->createFromNameAndData('mukurtu.settings', ['related_content_preview' => ['digital_heritage' => 'thumbnails']]);
    $this->assertCount(0, $valid->validate());
    $invalid = $typed_config->createFromNameAndData('mukurtu.settings', ['related_content_preview' => ['digital_heritage' => 'carousel']]);
    $this->assertCount(1, $invalid->validate());
  }

}
