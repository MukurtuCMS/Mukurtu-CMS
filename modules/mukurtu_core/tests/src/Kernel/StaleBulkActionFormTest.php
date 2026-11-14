<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_core\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\Core\Url;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views_bulk_operations\Form\ConfigureAction;
use Drupal\views_bulk_operations\Form\ConfirmAction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests VBO's configure and confirmation pages after an action has run.
 *
 * Builds VBO's real forms, so the test fails if VBO stops storing the
 * selection where the alter looks for it.
 */
#[Group('mukurtu_core')]
class StaleBulkActionFormTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'geofield',
    'leaflet',
    'node',
    'views',
    'views_bulk_operations',
    'mukurtu_core',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->container->get('router.builder')->rebuild();
    // Take uid 1, which bypasses access checks.
    $this->createUser();
  }

  /**
   * Saves the empty selection that loading a VBO list leaves behind.
   */
  protected function saveListSelection(): void {
    $this->container->get('tempstore.private')
      ->get('views_bulk_operations_bulk_test_list')
      ->set((string) $this->container->get('current_user')->id(), [
        'view_id' => 'bulk_test',
        'display_id' => 'list',
        'list' => [],
        'exclude_mode' => FALSE,
        'redirect_url' => Url::fromRoute('entity.user.collection', [], ['query' => ['type' => 'test']]),
      ]);
  }

  /**
   * The VBO forms that read the saved selection.
   */
  public static function formProvider(): array {
    return [
      'configure' => [ConfigureAction::class],
      'confirm' => [ConfirmAction::class],
    ];
  }

  /**
   * A stale page explains itself and links back to the list.
   */
  #[DataProvider('formProvider')]
  public function testStalePageExplains(string $form_class): void {
    $this->setUpCurrentUser([], ['administer users']);
    $this->saveListSelection();
    $form = $this->container->get('form_builder')->getForm($form_class, 'bulk_test', 'list');

    $this->assertSame(
      'This action has already run, or your selection has expired. Nothing was changed.',
      (string) $form['mukurtu_stale_selection']['#value'],
    );
    $this->assertArrayNotHasKey('#markup', $form);
    $this->assertSame('Select items again', (string) $form['mukurtu_select_again']['#title']);
    $this->assertSame('/admin/people?type=test', $form['mukurtu_select_again']['#url']->toString());
  }

  /**
   * Submitting a stale page doesn't reach VBO's handlers.
   */
  #[DataProvider('formProvider')]
  public function testStaleSubmitDoesNothing(string $form_class): void {
    $this->setUpCurrentUser([], ['administer users']);
    $this->saveListSelection();
    $form_state = (new FormState())->addBuildInfo('args', ['bulk_test', 'list']);
    // Without the alter, VBO throws "The "" plugin does not exist".
    $this->container->get('form_builder')->submitForm($form_class, $form_state);

    $this->assertSame([], $form_state->getErrors());
    $this->assertFalse($form_state->getRedirect());
  }

  /**
   * No link to a list the editor can't open.
   */
  public function testNoLinkWithoutAccess(): void {
    $this->setUpCurrentUser();
    $this->saveListSelection();
    $form = $this->container->get('form_builder')->getForm(ConfigureAction::class, 'bulk_test', 'list');

    $this->assertArrayHasKey('mukurtu_stale_selection', $form);
    $this->assertArrayNotHasKey('mukurtu_select_again', $form);
  }

  /**
   * A form with a saved action is left alone.
   */
  public function testLiveSelectionUntouched(): void {
    $form = ['#markup' => 'VBO form'];
    $form_state = (new FormState())->set('views_bulk_operations', ['action_id' => 'test']);
    $this->container->get('module_handler')->alter('form_views_bulk_operations_configure_action', $form, $form_state);

    $this->assertSame(['#markup' => 'VBO form'], $form);
  }

}
