<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_import\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mukurtu_import\Form\BulkEditGridForm;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests saving the per-item grid.
 *
 * Only changed cells are sent. Good rows save, bad ones stay in the grid
 * with their errors, and a cell someone else changed isn't overwritten
 * unasked.
 */
#[Group('mukurtu_import')]
class BulkEditGridSaveTest extends MukurtuImportTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['views_bulk_operations', 'mukurtu_export', 'mukurtu_multipage_items'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installSchema('user', ['users_data']);
    // Mukurtu Export needs Multipage Items, whose hooks run on each save.
    $this->installEntitySchema('multipage_item');
  }

  /**
   * Creates an item.
   */
  protected function item(string $title): NodeInterface {
    $node = Node::create([
      'title' => $title,
      'type' => 'protocol_aware_content',
      'status' => TRUE,
      'uid' => $this->currentUser->id(),
    ]);
    $node->setSharingSetting('any');
    $node->setProtocols([$this->protocol]);
    $node->save();
    return $node;
  }

  /**
   * Reloads an item.
   */
  protected function reload(NodeInterface $node): NodeInterface {
    $storage = $this->entityTypeManager->getStorage('node');
    $storage->resetCache([$node->id()]);
    return $storage->load($node->id());
  }

  /**
   * Counts an item's revisions.
   */
  protected function revisions(NodeInterface $node): int {
    return (int) $this->entityTypeManager->getStorage('node')->getQuery()
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('nid', $node->id())
      ->count()
      ->execute();
  }

  /**
   * Returns the content bulk edit page's VBO selection store.
   */
  protected function store() {
    return $this->container->get('tempstore.private')->get('views_bulk_operations_mukurtu_bulk_edit_content_page');
  }

  /**
   * Selects items on the content bulk edit page.
   */
  protected function select(NodeInterface ...$nodes): void {
    $list = [];
    foreach ($nodes as $node) {
      $list[] = [$node->id(), 'en', 'node', $node->id()];
    }
    $this->store()->set((string) $this->currentUser->id(), [
      'list' => $list,
      'view_id' => 'mukurtu_bulk_edit_content',
      'display_id' => 'page',
      'action_id' => 'mukurtu_edit_items_grid_action',
      'exclude_mode' => FALSE,
      'redirect_url' => Url::fromRoute('<front>'),
    ]);
  }

  /**
   * Submits the grid with titles keyed by item.
   */
  protected function submit(array $titles, ?FormStateInterface $form_state = NULL): FormStateInterface {
    $grid = [];
    foreach ($titles as $nid => $title) {
      $grid["$nid-en"]['title'] = $title;
    }
    $form_state ??= new FormState();
    $form_state->setValues(['grid' => $grid, 'op' => 'Save changes']);
    $this->container->get('form_builder')->submitForm(BulkEditGridForm::class, $form_state, 'mukurtu_bulk_edit_content', 'page');
    return $form_state;
  }

  /**
   * Returns the status and error messages, then clears them.
   */
  protected function messages(): array {
    return array_map(fn($list) => array_map('strval', $list), $this->container->get('messenger')->deleteAll());
  }

  /**
   * Good rows save; a bad row stays with its error; untouched rows don't.
   */
  public function testPartialSave(): void {
    $good = $this->item('Canoe');
    $bad = $this->item('Basket');
    $same = $this->item('Paddle');
    $revisions = [$bad->id() => $this->revisions($bad), $same->id() => $this->revisions($same)];
    $this->select($good, $bad, $same);

    $form_state = $this->submit([
      $good->id() => 'Carved canoe',
      $bad->id() => '',
      $same->id() => 'Paddle',
    ]);

    $good = $this->reload($good);
    $this->assertSame('Carved canoe', $good->label());
    $this->assertSame('Edited as a part of bulk operation. Field changed: Title', $good->getRevisionLogMessage());
    $this->assertSame('Basket', $this->reload($bad)->label());
    $this->assertSame($revisions[$bad->id()], $this->revisions($bad));
    $this->assertSame($revisions[$same->id()], $this->revisions($same));

    $key = $bad->id() . '-en';
    $this->assertSame([$key], $form_state->get('grid_remaining'));
    $this->assertArrayHasKey('title', $form_state->get('grid_errors')[$key]);
    $messages = $this->messages();
    $this->assertSame(['Saved 1 item.'], $messages['status']);
    $this->assertSame(["1 item couldn't be saved. Fix the values below and save again."], $messages['error']);
    $this->assertNotNull($this->store()->get((string) $this->currentUser->id()));
  }

  /**
   * Saving with nothing changed says so, clears the selection and leaves.
   */
  public function testNothingChanged(): void {
    $node = $this->item('Canoe');
    $this->select($node);

    $form_state = $this->submit([$node->id() => 'Canoe']);

    $this->assertSame(['Nothing was changed.'], $this->messages()['status']);
    $this->assertNull($this->store()->get((string) $this->currentUser->id()));
    // Drupal hides the redirect of a programmed submission.
    $this->assertSame('<front>', $form_state->setProgrammed(FALSE)->getRedirect()->getRouteName());
  }

  /**
   * A cell someone else changed after the grid opened isn't overwritten.
   */
  public function testSomeoneElseChanged(): void {
    $node = $this->item('Canoe');
    $this->select($node);
    $key = $node->id() . '-en';
    // The grid opened when the title was still "Old canoe".
    $form_state = new FormState();
    $form_state->set('grid_originals', [$key => ['title' => 'Old canoe']]);

    $this->submit([$node->id() => 'My canoe'], $form_state);

    $this->assertSame('Canoe', $this->reload($node)->label());
    $this->assertSame(
      ["Someone else changed this after you opened the page. It's now: Canoe. Save again to replace it with yours."],
      $form_state->get('grid_errors')[$key]['title'],
    );
    // Saving again now replaces it on purpose.
    $this->assertSame('Canoe', $form_state->get('grid_originals')[$key]['title']);
  }

}
