<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_dictionary\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\mukurtu_dictionary\Controller\MukurtuDictionaryController;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\views\Entity\View;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Tests that the Dictionary access check ignores the current page's URL.
 *
 * The main menu runs this check on every page, and it executes the dictionary
 * view. With the current URL as its exposed input, a sort that belongs to the
 * page's own view failed the dictionary view's validation (#2621).
 *
 * @see \Drupal\mukurtu_dictionary\Controller\MukurtuDictionaryController::access()
 */
#[Group('mukurtu_dictionary')]
class DictionaryAccessExposedInputTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'mukurtu_dictionary',
    'node',
    'system',
    'text',
    'user',
    'views',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installConfig(['system', 'views']);

    // A stand-in for the dictionary view, with its own exposed sort. The real
    // view needs a search index.
    View::create([
      'id' => 'mukurtu_dictionary',
      'base_table' => 'users_field_data',
      'display' => [
        'default' => [
          'id' => 'default',
          'display_plugin' => 'default',
          'display_title' => 'Default',
          'position' => 0,
          'display_options' => [
            'access' => ['type' => 'none'],
            'exposed_form' => ['type' => 'basic'],
            'sorts' => [
              'uid' => [
                'id' => 'uid',
                'table' => 'users_field_data',
                'field' => 'uid',
                'entity_type' => 'user',
                'plugin_id' => 'standard',
                'order' => 'ASC',
                'exposed' => TRUE,
                'expose' => ['label' => 'Glossary', 'field_identifier' => 'glossary_order'],
              ],
            ],
          ],
        ],
      ],
    ])->save();
    $this->createUser();
  }

  /**
   * Makes a request to a page with another view's sort in its URL.
   */
  protected function visitPageSortedBy(string $sort): void {
    $request = Request::create('/digital-heritage', 'GET', ['sort_by' => $sort]);
    $request->setSession(new Session(new MockArraySessionStorage()));
    $this->container->get('request_stack')->push($request);
  }

  /**
   * Runs the access check.
   */
  protected function checkAccess(): bool {
    $controller = $this->container->get('class_resolver')->getInstanceFromDefinition(MukurtuDictionaryController::class);
    return $controller->access($this->setUpCurrentUser())->isAllowed();
  }

  /**
   * A sort from another view shows no error.
   */
  public function testAnotherViewsSortShowsNoError(): void {
    $this->visitPageSortedBy('newest');

    $this->assertTrue($this->checkAccess());
    $this->assertSame([], $this->container->get('messenger')->all());
  }

}
