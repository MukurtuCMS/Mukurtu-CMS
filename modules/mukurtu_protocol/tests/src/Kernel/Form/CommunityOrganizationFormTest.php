<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Form\CommunityOrganizationForm;
use Drupal\Tests\mukurtu_protocol\Kernel\ProtocolAwareEntityTestBase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the community organization form against out-of-date saved settings.
 */
#[CoversClass(CommunityOrganizationForm::class)]
#[Group('mukurtu_protocol')]
class CommunityOrganizationFormTest extends ProtocolAwareEntityTestBase {

  /**
   * A community ID that doesn't belong to any community.
   */
  protected const MISSING_ID = 9999;

  /**
   * A top-level community.
   */
  protected CommunityInterface $parent;

  /**
   * A child of $parent.
   */
  protected CommunityInterface $child;

  /**
   * A community whose saved parent no longer exists.
   */
  protected CommunityInterface $orphan;

  /**
   * A community that isn't in the saved organization.
   */
  protected CommunityInterface $unlisted;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->parent = $this->createCommunity('Parent');
    $this->child = $this->createCommunity('Child');
    $this->orphan = $this->createCommunity('Orphan');
    $this->unlisted = $this->createCommunity('Unlisted');

    // Replace the organization that Community::postSave() built with one
    // that references a missing community and leaves one community out.
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', [
        static::MISSING_ID => ['parent' => 0, 'weight' => 0],
        $this->parent->id() => ['parent' => 0, 'weight' => 1],
        $this->child->id() => ['parent' => $this->parent->id(), 'weight' => 0],
        $this->orphan->id() => ['parent' => static::MISSING_ID, 'weight' => 2],
      ])
      ->save();
  }

  /**
   * Creates and saves a community.
   */
  protected function createCommunity(string $name): CommunityInterface {
    $community = Community::create([
      'name' => $name,
      'status' => TRUE,
      'field_access_mode' => 'public',
    ]);
    $community->save();
    return $community;
  }

  /**
   * Tests that the form builds rows for existing communities only.
   */
  public function testBuildFormSkipsMissingCommunities(): void {
    $form = $this->container->get('form_builder')->getForm(CommunityOrganizationForm::class);
    $rows = $form['communities'];

    $this->assertArrayNotHasKey(static::MISSING_ID, $rows);
    // The parent input sits in the community cell, so there's no Parent column.
    $this->assertCount(2, $rows['#header']);

    $this->assertSame(0, $rows[$this->parent->id()]['label']['parent']['#default_value']);
    $this->assertSame(0, $rows[$this->parent->id()]['label'][0]['#size']);

    $this->assertEquals($this->parent->id(), $rows[$this->child->id()]['label']['parent']['#default_value']);
    $this->assertSame(1, $rows[$this->child->id()]['label'][0]['#size']);

    $this->assertSame(0, $rows[$this->orphan->id()]['label']['parent']['#default_value']);
    $this->assertSame(0, $rows[$this->orphan->id()]['label'][0]['#size']);

    $this->assertArrayHasKey($this->unlisted->id(), $rows);
    $this->assertSame(0, $rows[$this->unlisted->id()]['label']['parent']['#default_value']);
    $this->assertSame(3, $rows[$this->unlisted->id()]['weight']['#default_value']);
  }

  /**
   * Tests that a parent loop doesn't hang the form.
   */
  public function testBuildFormHandlesParentLoop(): void {
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', [
        $this->parent->id() => ['parent' => $this->child->id(), 'weight' => 0],
        $this->child->id() => ['parent' => $this->parent->id(), 'weight' => 0],
      ])
      ->save();

    $form = $this->container->get('form_builder')->getForm(CommunityOrganizationForm::class);

    $this->assertSame(1, $form['communities'][$this->parent->id()]['label'][0]['#size']);
    $this->assertSame(1, $form['communities'][$this->child->id()]['label'][0]['#size']);
  }

  /**
   * Tests that saving the form removes the missing community.
   */
  public function testSubmitSavesOnlyExistingCommunities(): void {
    $form_state = (new FormState())->setValues(['op' => 'Save']);
    $this->container->get('form_builder')->submitForm(CommunityOrganizationForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    $org = $this->config('mukurtu_protocol.community_organization')->get('organization');

    $this->assertArrayNotHasKey(static::MISSING_ID, $org);
    $this->assertEqualsCanonicalizing(
      [$this->parent->id(), $this->child->id(), $this->orphan->id(), $this->unlisted->id()],
      array_keys($org),
    );
    $this->assertSame($this->parent->id(), (string) $org[$this->child->id()]['parent']);
    $this->assertSame(0, $org[$this->orphan->id()]['parent']);
    $this->assertSame(0, $org[$this->unlisted->id()]['parent']);
  }

  /**
   * Submits the form with the given parent for each community.
   *
   * @param array $parents
   *   Parent IDs, keyed by community ID.
   *
   * @return array
   *   The saved organization.
   */
  protected function submitParents(array $parents): array {
    $rows = [];
    $weight = 0;
    foreach ($parents as $id => $parent) {
      $rows[$id] = ['weight' => $weight++, 'parent' => $parent];
    }
    $form_state = (new FormState())->setValues(['communities' => $rows, 'op' => 'Save']);
    $this->container->get('form_builder')->submitForm(CommunityOrganizationForm::class, $form_state);
    $this->assertSame([], $form_state->getErrors());

    return $this->config('mukurtu_protocol.community_organization')->get('organization');
  }

  /**
   * Tests that a parent with an ID above the weight select range is saved.
   */
  public function testSubmitSavesParentWithHighId(): void {
    do {
      $highParent = $this->createCommunity('High ID parent');
    } while ($highParent->id() <= 10);

    $parents = array_fill_keys(array_keys(Community::loadMultiple()), 0);
    $parents[$this->child->id()] = $highParent->id();
    $org = $this->submitParents($parents);

    $this->assertEquals($highParent->id(), $org[$this->child->id()]['parent']);
  }

  /**
   * Tests that a missing or self-referencing parent is saved as top level.
   */
  public function testSubmitDropsInvalidParent(): void {
    $org = $this->submitParents([
      $this->parent->id() => 0,
      $this->child->id() => static::MISSING_ID,
      $this->orphan->id() => $this->orphan->id(),
      $this->unlisted->id() => $this->parent->id(),
    ]);

    $this->assertSame(0, $org[$this->child->id()]['parent']);
    $this->assertSame(0, $org[$this->orphan->id()]['parent']);
    $this->assertEquals($this->parent->id(), $org[$this->unlisted->id()]['parent']);
  }

}
