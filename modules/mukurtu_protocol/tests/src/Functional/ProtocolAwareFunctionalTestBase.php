<?php

namespace Drupal\Tests\mukurtu_protocol\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\node\Entity\Node;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\CulturalProtocolControlledInterface;

/**
 * Functional test base for protocol aware content.
 *
 * @group mukurtu_protocol
 */
class ProtocolAwareFunctionalTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [];

  /**
   * {@inheritDoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritDoc}
   */
  protected $profile = 'mukurtu';

  /**
   * Community 1.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community1;

  /**
   * Open protocol for Community 1.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterfacae
   */
  protected $community1_open;

  /**
   * Strict protocol for Community 1.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterfacae
   */
  protected $community1_strict;

  /**
   * Community 2.
   *
   * @var \Drupal\mukurtu_protocol\Entity\CommunityInterface
   */
  protected $community2;

  /**
   * Open protocol for Community 2.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterfacae
   */
  protected $community2_open;

  /**
   * Strict protocol for Community 2.
   *
   * @var \Drupal\mukurtu_protocol\Entity\ProtocolInterfacae
   */
  protected $community2_strict;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create our two test communities.
    $community = Community::create([
      'name' => 'Community 1',
    ]);
    $community->save();
    $this->community1 = $community;

    $community = Community::create([
      'name' => 'Community 2',
    ]);
    $community->save();
    $this->community2 = $community;

    // Create the protocols for those communities.
    $protocol = Protocol::create([
      'name' => "Community 1 Open",
      'field_communities' => [$this->community1->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $this->community1_open = $protocol;

    $protocol = Protocol::create([
      'name' => "Community 1 Strict",
      'field_communities' => [$this->community1->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();
    $this->community1_strict = $protocol;

    $protocol = Protocol::create([
      'name' => "Community 2 Open",
      'field_communities' => [$this->community2->id()],
      'field_access_mode' => 'open',
    ]);
    $protocol->save();
    $this->community2_open = $protocol;

    $protocol = Protocol::create([
      'name' => "Community 2 Strict",
      'field_communities' => [$this->community2->id()],
      'field_access_mode' => 'strict',
    ]);
    $protocol->save();
    $this->community2_strict = $protocol;
  }

  /**
   * Create a protocol aware node.
   *
   * @param array $values
   *   The values to pass to Node::create.
   * @param \Drupal\mukurtu_protocol\Entity\ProtocolInterface[] $protocols
   *   The protocol entities the new node should use.
   * @param string $sharing_setting
   *   The privacy setting the new node should use.
   *
   * @return \Drupal\node\NodeInterface
   *   The created node.
   */
  protected function mukurtuCreateNode($values, $protocols, $sharing_setting = 'all') {
    $content = Node::create($values);

    if ($content instanceof CulturalProtocolControlledInterface) {
      $content->setProtocols(array_map(fn ($p): int => $p->id(), $protocols));
      $content->setSharingSetting($sharing_setting);
    }
    $content->save();

    return $content;
  }

}
