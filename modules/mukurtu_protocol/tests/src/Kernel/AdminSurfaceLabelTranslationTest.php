<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_protocol\Entity\Community;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Form\AddMemberToCommunityForm;
use Drupal\mukurtu_protocol\Form\AddUserToCommunityForm;
use Drupal\mukurtu_protocol\ProtocolListBuilder;

/**
 * Tests that admin/editor-facing surfaces show protocol/community names in
 * the active content language, covering the same anti-pattern fixed for
 * visitor-facing surfaces in #1671 (see ProtocolLabelTranslationTest).
 *
 * @group mukurtu_protocol
 */
class AdminSurfaceLabelTranslationTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
  ];

  protected Community $community;

  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('protocol', 'protocol', TRUE);
    \Drupal::service('content_translation.manager')->setEnabled('community', 'community', TRUE);

    $this->community = Community::create(['name' => 'Open Community']);
    $this->community->save();
    $this->community->addTranslation('es', ['name' => 'Comunidad Abierta'])->save();

    $this->protocol = Protocol::create([
      'name' => 'Open Protocol',
      'field_communities' => [['target_id' => $this->community->id()]],
    ]);
    $this->protocol->save();
    $this->protocol->addTranslation('es', ['name' => 'Protocolo Abierto'])->save();

    $languageManager = \Drupal::languageManager();
    $property = new \ReflectionProperty($languageManager, 'negotiatedLanguages');
    $property->setAccessible(TRUE);
    $property->setValue($languageManager, [LanguageInterface::TYPE_CONTENT => new Language(['id' => 'es'])]);
  }

  public function testProtocolListBuilderShowsTranslatedNames(): void {
    $definition = \Drupal::entityTypeManager()->getDefinition('protocol');
    $listBuilder = ProtocolListBuilder::createInstance(\Drupal::getContainer(), $definition);
    $build = $listBuilder->render();

    $rendered = (string) \Drupal::service('renderer')->renderInIsolation($build);
    $this->assertStringContainsString('Comunidad Abierta', $rendered);
    $this->assertStringContainsString('Protocolo Abierto', $rendered);
    $this->assertStringNotContainsString('Open Community', $rendered);
    $this->assertStringNotContainsString('Open Protocol', $rendered);
  }

  public function testAddMemberToCommunityFormShowsTranslatedNames(): void {
    $user = $this->createUser();

    $form = AddMemberToCommunityForm::create(\Drupal::getContainer());
    $method = new \ReflectionMethod($form, 'getProtocolsForCommunity');
    $method->setAccessible(TRUE);
    $rows = $method->invoke($form, $this->community, $user);

    $row = reset($rows);
    $this->assertEquals('Comunidad Abierta', $row['community_name']);
    $this->assertEquals('Protocolo Abierto', $row['protocol_name']);
  }

  public function testAddUserToCommunityFormShowsTranslatedNames(): void {
    $user = $this->createUser();

    $form = AddUserToCommunityForm::create(\Drupal::getContainer());

    $availableMethod = new \ReflectionMethod($form, 'getAvailableCommunities');
    $availableMethod->setAccessible(TRUE);
    // Grant the current user (used internally via $this->currentUser()) an
    // admin role so the admin branch (loads and translates all communities)
    // is the one under test.
    $this->setCurrentUser($this->createUser([], NULL, TRUE));
    $communities = $availableMethod->invoke($form, $user);
    $this->assertEquals('Comunidad Abierta', reset($communities)->getName());

    $protocolsMethod = new \ReflectionMethod($form, 'getProtocolsForCommunities');
    $protocolsMethod->setAccessible(TRUE);
    $rows = $protocolsMethod->invoke($form, [$this->community->id()], $user);
    $row = reset($rows);
    $this->assertEquals('Comunidad Abierta', $row['community_name']);
    $this->assertEquals('Protocolo Abierto', $row['protocol_name']);
  }

}
