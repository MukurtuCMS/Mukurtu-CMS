<?php

namespace Drupal\message_digest;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\user\UserInterface;

/**
 * Message digest formatter service.
 */
class DigestFormatter implements DigestFormatterInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * Construct the digest formatter service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The rendering service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public function format(array $digest, array $view_modes, UserInterface $recipient) {
    $output = [
      '#theme' => 'message_digest',
      '#messages' => [],
    ];
    foreach ($digest as $message) {
      // Set the user to the recipient. This is similar to how message_subscribe
      // works when sending a message to many different users.
      $message->setOwner($recipient);

      $rows = [
        '#theme' => 'message_digest_rows',
        '#message' => $message,
      ];
      foreach ($view_modes as $view_mode) {
        $build = $this->entityTypeManager->getViewBuilder('message')->view($message, $view_mode, $recipient->getPreferredLangcode());
        $rows[] = $build;
      }
      $output[] = $rows;
    }

    return $this->renderer->renderPlain($output);
  }

}
