<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Component\Serialization\Yaml;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Plugin\Field\CommunityChildCommunitiesItemList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the computed child communities field.
 */
#[CoversClass(CommunityChildCommunitiesItemList::class)]
#[Group('mukurtu_protocol')]
class CommunityChildCommunitiesTest extends ProtocolAwareEntityTestBase {

  /**
   * A community ID that doesn't belong to any community.
   */
  protected const MISSING_ID = 9999;

  /**
   * Builds an unsaved community.
   */
  protected function buildCommunity(string $name): CommunityInterface {
    return Community::create([
      'name' => $name,
      'status' => TRUE,
      'field_access_mode' => 'public',
    ]);
  }

  /**
   * An unsaved community has no children, even with top-level entries.
   *
   * Reproduces #2711: the organization settings held a top-level entry for a
   * community that didn't exist, so validating a new community failed with
   * "The referenced entity (community: ...) does not exist."
   */
  public function testNewCommunityHasNoChildren(): void {
    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', [
        static::MISSING_ID => ['parent' => 0, 'weight' => 0],
      ])
      ->save();

    $community = $this->buildCommunity('New community');

    $this->assertTrue($community->get('field_child_communities')->isEmpty());
    $this->assertFalse($community->isParentCommunity());
    $violations = $community->validate()->getByField('field_child_communities');
    $this->assertCount(0, $violations, (string) $violations);
  }

  /**
   * A saved community lists only the communities whose parent it is.
   */
  public function testSavedCommunityListsItsChildren(): void {
    $parent = $this->buildCommunity('Parent');
    $parent->save();
    $child = $this->buildCommunity('Child');
    $child->save();
    $other = $this->buildCommunity('Other');
    $other->save();

    $this->config('mukurtu_protocol.community_organization')
      ->set('organization', [
        $parent->id() => ['parent' => 0, 'weight' => 0],
        $child->id() => ['parent' => (string) $parent->id(), 'weight' => 0],
        $other->id() => ['parent' => 0, 'weight' => 1],
      ])
      ->save();

    $parent = Community::load($parent->id());
    $child_ids = array_column($parent->get('field_child_communities')->getValue(), 'target_id');
    $this->assertEquals([$child->id()], $child_ids);

    $other = Community::load($other->id());
    $this->assertTrue($other->get('field_child_communities')->isEmpty());
  }

  /**
   * The profile ships an empty organization, with no stale community IDs.
   */
  public function testShippedOrganizationIsEmpty(): void {
    $path = dirname(__DIR__, 5) . '/config/install/mukurtu_protocol.community_organization.yml';
    $this->assertFileExists($path);
    $data = Yaml::decode(file_get_contents($path));
    $this->assertSame([], $data['organization']);
  }

}
