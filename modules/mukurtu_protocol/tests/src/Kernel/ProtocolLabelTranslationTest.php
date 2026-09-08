<?php

declare(strict_types=1);

namespace Drupal\Tests\mukurtu_protocol\Kernel;

use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageInterface;
use Drupal\facets\Entity\Facet;
use Drupal\facets\Result\Result;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\mukurtu_protocol\Entity\Protocol;
use Drupal\mukurtu_protocol\Plugin\facets\processor\CulturalProtocolFacetLabelProcessor;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests that protocol names shown to visitors respect the active content
 * language, instead of always showing the entity's original-language name.
 *
 * This is the exact bug reported in #1671: a Chinese-interface site showed
 * an English protocol name in facet results because the facet label
 * processor loaded the protocol entity and read ->getName() directly,
 * bypassing entity.repository's translation context. The same anti-pattern
 * existed in MediaProtocolFilter/NodeProtocolFilter's exposed-filter option
 * lists.
 */
#[Group('mukurtu_protocol')]
class ProtocolLabelTranslationTest extends ProtocolAwareEntityTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'content_translation',
    'views',
    'facets',
  ];

  /**
   * The translated protocol used across all test methods.
   */
  protected Protocol $protocol;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // 'en' isn't a real ConfigurableLanguage entity until explicitly created
    // (KernelTestBase's $modules doesn't run language module's real install
    // process, which normally does this) - without it, only 'es' would be
    // "configured", ConfigurableLanguageManager::isMultilingual() would
    // return FALSE, and getFallbackCandidates()/getTranslationFromContext()
    // would silently never consider 'es' as a translation target at all.
    ConfigurableLanguage::createFromLangcode('en')->save();
    ConfigurableLanguage::createFromLangcode('es')->save();
    \Drupal::service('content_translation.manager')->setEnabled('protocol', 'protocol', TRUE);

    $this->protocol = Protocol::create([
      'name' => 'Open Protocol',
    ]);
    $this->protocol->save();
    $this->protocol->addTranslation('es', ['name' => 'Protocolo Abierto'])->save();

    // Simulate a visitor whose active content language is Spanish while the
    // site default stays English - the real #1671 scenario. There's no
    // negotiator configured in a kernel test (no real HTTP request to
    // negotiate from), so the current content language must be injected
    // directly; ConfigurableLanguageManager exposes no public setter for it.
    $languageManager = \Drupal::languageManager();
    $property = new \ReflectionProperty($languageManager, 'negotiatedLanguages');
    $property->setAccessible(TRUE);
    $property->setValue($languageManager, [LanguageInterface::TYPE_CONTENT => new Language(['id' => 'es'])]);
  }

  public function testFacetLabelProcessorShowsTranslatedName(): void {
    $facet = Facet::create(['id' => 'test_facet']);
    // The processor trims and loads by getDisplayValue(), not the raw value -
    // it runs after the facet source has already put the raw protocol ID,
    // pipe-delimited, into the display value (matching the pipe-delimited
    // storage format field_cultural_protocols__protocols uses).
    $result = new Result($facet, $this->protocol->id(), '|' . $this->protocol->id() . '|', 1);

    $processor = new CulturalProtocolFacetLabelProcessor([], 'cultural_protocol_facet_label_processor', []);
    $results = $processor->build($facet, [$result]);

    $this->assertEquals('Protocolo Abierto', reset($results)->getDisplayValue());
  }

  public function testNodeProtocolFilterShowsTranslatedName(): void {
    $filter = \Drupal::service('plugin.manager.views.filter')->createInstance('mukurtu_node_protocol_filter');
    $options = $filter->getValueOptions();

    $this->assertEquals('Protocolo Abierto', $options[$this->protocol->id()]);
  }

  public function testMediaProtocolFilterShowsTranslatedName(): void {
    $filter = \Drupal::service('plugin.manager.views.filter')->createInstance('mukurtu_media_protocol_filter');
    $options = $filter->getValueOptions();

    $this->assertEquals('Protocolo Abierto', $options[$this->protocol->id()]);
  }

}
