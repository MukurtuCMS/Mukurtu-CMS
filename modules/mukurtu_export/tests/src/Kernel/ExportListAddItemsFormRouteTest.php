<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_export\Kernel;

use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_export\Form\ExportListAddItemsForm;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;

/**
 * Tests that the previously-missing mukurtu_export.add_items_to_list route
 * resolves, and that the form's empty-selection redirect branch (which
 * calls Url::fromUserInput(), previously unimported) doesn't fatal.
 */
#[Group('mukurtu_export')]
class ExportListAddItemsFormRouteTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'content_moderation',
    'field',
    'file',
    'flag',
    'geofield',
    'image',
    'leaflet',
    'media',
    'mukurtu_core',
    'mukurtu_export',
    'mukurtu_multipage_items',
    'node',
    'options',
    'system',
    'taxonomy',
    'text',
    'user',
    'views',
    'views_bulk_operations',
    'workflows',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('export_list');
  }

  /**
   * The route resolves after a router rebuild -- confirms the route
   * definition is present and well-formed.
   */
  public function testAddItemsToListRouteResolves(): void {
    \Drupal::service('router.builder')->rebuild();
    $route = \Drupal::service('router.route_provider')->getRouteByName('mukurtu_export.add_items_to_list');
    $this->assertSame('Drupal\mukurtu_export\Form\ExportListAddItemsForm', $route->getDefault('_form'));
  }

  /**
   * With a staged VBO selection that carries no 'action_id' (matching what
   * a real visit leaves behind once the selection has already been
   * processed/cleared), buildForm() takes the Url::fromUserInput()
   * redirect branch without fataling on a missing class import.
   */
  public function testBuildFormRedirectsWithoutFatalOnEmptySelection(): void {
    $user = \Drupal\user\Entity\User::create(['name' => $this->randomMachineName()]);
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    // getFormData() calls addListData(array &$form_data), which requires
    // a real array (a truly-missing tempstore key returns NULL and
    // fatals before buildForm()'s own code even runs) -- so seed an empty
    // but present selection, matching a real "nothing left to act on"
    // tempstore state.
    $this->container->get('tempstore.private')
      ->get('views_bulk_operations_mukurtu_manage_all_content_mukurtu_manage_content')
      ->set((string) $user->id(), ['list' => []]);

    $request = Request::create('/admin/export/add-items/mukurtu_manage_all_content/mukurtu_manage_content');
    $request->query->set('destination', '/admin/content');
    $request->setSession($this->container->get('session'));
    \Drupal::requestStack()->push($request);

    $form_object = ExportListAddItemsForm::create($this->container);
    $form_state = new FormState();
    $build = $form_object->buildForm([], $form_state, 'mukurtu_manage_all_content', 'mukurtu_manage_content');

    $this->assertIsArray($build);
    $redirect = $form_state->getRedirect();
    $this->assertNotNull($redirect, 'Expected a redirect to be set instead of fataling.');
  }

}
