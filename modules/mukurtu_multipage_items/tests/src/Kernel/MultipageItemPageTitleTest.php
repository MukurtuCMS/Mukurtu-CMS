<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_multipage_items\Kernel;

use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Render\Element;
use Drupal\mukurtu_multipage_items\Controller\MultipageItemPageController;

/**
 * Tests the current page heading on multipage items (#598).
 *
 * @group mukurtu_multipage_items
 */
#[\PHPUnit\Framework\Attributes\Group('mukurtu_multipage_items')]
class MultipageItemPageTitleTest extends MultipageItemTestBase {

  /**
   * Asserts a render array holds the page heading followed by the page.
   */
  protected function assertPageHeading(array $build, string $expected_value, int $nid): void {
    $this->assertSame(['title', 'page'], Element::children($build));
    $this->assertSame('html_tag', $build['title']['#type']);
    $this->assertSame('h2', $build['title']['#tag']);
    $this->assertSame($expected_value, $build['title']['#value']);
    $this->assertArrayHasKey('data-mukurtu-page-title', $build['title']['#attributes']);
    $this->assertSame($nid, (int) $build['page']['#node']->id());
  }

  /**
   * The initial view and the AJAX page switch both show the page heading.
   */
  public function testPageHeading(): void {
    $first = $this->buildItem('First page');
    $first->save();
    $second = $this->buildItem('Second <b>page</b>');
    $second->save();
    $mpi = $this->buildMultipageItem('Notebook');
    $mpi->addPage($first);
    $mpi->addPage($second);
    $mpi->save();

    $controller = MultipageItemPageController::create(\Drupal::getContainer());

    $build = $controller->view($second);
    // The title is text, so markup in it is escaped rather than rendered.
    $this->assertPageHeading($build['#current_page'], 'Second &lt;b&gt;page&lt;/b&gt;', (int) $second->id());

    // Read the command unrendered; getCommands() would render the node.
    $response = $controller->getSelectedPageAjax($first);
    $commands = (new \ReflectionProperty($response, 'commands'))->getValue($response);
    $this->assertCount(1, $commands);
    $command = $commands[0];
    $this->assertInstanceOf(ReplaceCommand::class, $command);
    $this->assertSame('#mukurtu-multipage-item-selected-page', (new \ReflectionProperty($command, 'selector'))->getValue($command));
    $content = (new \ReflectionProperty($command, 'content'))->getValue($command);
    $this->assertPageHeading($content['mukurtu_multipage_item_selected_page'], 'First page', (int) $first->id());
  }

}
