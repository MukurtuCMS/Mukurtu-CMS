<?php

/**
 * @file
 * Contains \Drupal\mukurtu_footer\Plugin\Block\MukurtuFooterBlock.
 */

namespace Drupal\mukurtu_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Mukurtu Footer block.
 *
 * Configuration is managed via the dedicated settings form at
 * /admin/config/mukurtu/footer (FooterSettingsForm). This block is a
 * thin renderer that reads from mukurtu_footer.settings config.
 *
 * @Block(
 *   id = "mukurtu_footer",
 *   admin_label = @Translation("Mukurtu Footer"),
 *   category = "Custom"
 * )
 */
class MukurtuFooterBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected ConfigFactoryInterface $configFactory,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected Token $token,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $config = $this->configFactory->get('mukurtu_footer.settings');
    $file_storage = $this->entityTypeManager->getStorage('file');

    // Resolve logo file entities to public URLs.
    $logos = [];
    foreach ($config->get('logos') ?? [] as $logo) {
      if (!empty($logo['fid'])) {
        $file = $file_storage->load($logo['fid']);
        if ($file) {
          $logos[] = [
            'url'      => $this->fileUrlGenerator->generateString($file->getFileUri()),
            'alt'      => $logo['alt'] ?? '',
            'link_url' => $logo['link_url'] ?? '',
          ];
        }
      }
    }

    // Replace tokens in the copyright message at render time (not at save time).
    $copyright = $config->get('copyright_message') ?? '';
    if ($copyright) {
      $copyright = $this->token->replace($copyright, [], ['clear' => TRUE]);
    }

    // Build a render array for the formatted text field.
    $text_field_config = $config->get('text_field') ?? [];
    $text_field = [];
    if (!empty($text_field_config['value'])) {
      $text_field = [
        '#type'   => 'processed_text',
        '#text'   => $text_field_config['value'],
        '#format' => $text_field_config['format'] ?? 'basic_html',
      ];
    }

    return [
      '#theme'                 => 'mukurtu_footer',
      '#text_field'            => $text_field,
      '#logos'                 => $logos,
      '#social_accounts'       => $config->get('social_accounts') ?? [],
      '#other_links'           => $config->get('other_links') ?? [],
      '#contact_email_text'    => $config->get('contact_email_text') ?? '',
      '#contact_email_address' => $config->get('contact_email_address') ?? '',
      '#copyright_message'     => $copyright,
      '#cache'                 => [
        'tags' => ['config:mukurtu_footer.settings'],
      ],
    ];
  }

}
