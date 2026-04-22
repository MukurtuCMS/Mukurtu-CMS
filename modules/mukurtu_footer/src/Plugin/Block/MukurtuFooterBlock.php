<?php

/**
 * @file
 * Contains \Drupal\mukurtu_footer\Plugin\Block\MukurtuFooterBlock.
 */

namespace Drupal\mukurtu_footer\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Utility\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the Mukurtu Footer block.
 *
 * Renders the first block_content entity of type 'mukurtu_footer'. Content
 * is edited via the standard block content UI at /admin/content/block-content.
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
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('token'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $storage = $this->entityTypeManager->getStorage('block_content');
    $entities = $storage->loadByProperties(['type' => 'mukurtu_footer']);
    $footer = reset($entities);

    if (!$footer) {
      return [];
    }

    if (count($entities) > 1) {
      \Drupal::logger('mukurtu_footer')->warning(
        'Multiple mukurtu_footer block_content entities found (@count). Using the first one (id: @id). Delete extras at /admin/content/block-content.',
        ['@count' => count($entities), '@id' => $footer->id()]
      );
    }

    // Resolve logo paragraph entities to renderable data.
    $logos = [];
    foreach ($footer->get('field_footer_logos') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }
      $image_item = $paragraph->get('field_footer_logo_image')->first();
      if (!$image_item) {
        continue;
      }
      $file = $image_item->entity;
      if ($file) {
        $link_item = $paragraph->get('field_footer_logo_link')->first();
        $logos[] = [
          'url'      => $this->fileUrlGenerator->generateString($file->getFileUri()),
          'alt'      => $image_item->alt ?? '',
          'link_url' => $link_item ? $link_item->getUrl()->toString() : '',
        ];
      }
    }

    // Build social links array from paragraph entities.
    $social_links = [];
    foreach ($footer->get('field_footer_social_links') as $item) {
      $paragraph = $item->entity;
      if (!$paragraph) {
        continue;
      }
      $url_item = $paragraph->get('field_footer_social_url')->first();
      if (!$url_item || !$url_item->uri) {
        continue;
      }
      $social_links[] = [
        'platform' => $paragraph->get('field_footer_social_platform')->value ?? '',
        'url'      => $url_item->getUrl()->toString(),
        'label'    => $url_item->title ?? '',
      ];
    }

    // Build other links array.
    $other_links = [];
    foreach ($footer->get('field_footer_other_links') as $item) {
      if ($item->uri) {
        $other_links[] = [
          'url'   => $item->getUrl()->toString(),
          'label' => $item->title ?? '',
        ];
      }
    }

    // Build footer text render array.
    $text_field = [];
    $body = $footer->get('body')->first();
    if ($body && !empty($body->value)) {
      $text_field = [
        '#type'   => 'processed_text',
        '#text'   => $body->value,
        '#format' => $body->format ?? 'basic_html',
      ];
    }

    // Replace tokens in copyright at render time.
    $copyright = $footer->get('field_footer_copyright')->value ?? '';
    if ($copyright) {
      $copyright = $this->token->replace($copyright, [], ['clear' => TRUE]);
    }

    return [
      '#theme'                 => 'mukurtu_footer',
      '#text_field'            => $text_field,
      '#logos'                 => $logos,
      '#social_links'          => $social_links,
      '#other_links'           => $other_links,
      '#contact_email_text'    => $footer->get('field_footer_contact_email_label')->value ?? '',
      '#contact_email_address' => $footer->get('field_footer_contact_email')->value ?? '',
      '#copyright_message'     => $copyright,
      '#cache'                 => [
        'tags' => array_merge(
          ['block_content_list'],
          $footer->getCacheTags(),
        ),
      ],
    ];
  }

}
