<?php

declare(strict_types=1);

namespace Drupal\mukurtu_media;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;

/**
 * Explains why a person can't add media.
 *
 * Media can only be created inside a cultural protocol that lets the person
 * add media. Without one, every way of adding media is closed, and core says
 * only "Access denied" or shows nothing at all. This supplies the reason.
 *
 * Access itself is unchanged; this only asks the media access handler.
 *
 * @see \Drupal\mukurtu_protocol\MukurtuProtocolMediaAccessControlHandler::checkCreateAccess()
 */
class MediaCreateNotice {

  use StringTranslationTrait;

  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * Checks whether the person is blocked from adding any of the media types.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The person trying to add media.
   * @param string[]|null $bundles
   *   The media types they are trying to add, or NULL for every media type.
   *
   * @return bool
   *   TRUE when the person is signed in and can't add any of the media types.
   */
  public function appliesTo(AccountInterface $account, ?array $bundles = NULL): bool {
    if ($account->isAnonymous()) {
      return FALSE;
    }
    $bundles ??= array_keys($this->entityTypeManager->getStorage('media_type')->loadMultiple());
    $handler = $this->entityTypeManager->getAccessControlHandler('media');
    foreach ($bundles as $bundle) {
      if ($handler->createAccess($bundle, $account)) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Returns the message that explains why the person can't add media.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The person trying to add media.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The message.
   */
  public function getMessage(AccountInterface $account): TranslatableMarkup {
    $add_community = Url::fromRoute('mukurtu_protocol.community_create');
    if (!$this->siteHasProtocols() && $add_community->access($account)) {
      return $this->t('Media must belong to a cultural protocol, and this site doesn\'t have one yet. <a href=":url">Add a community</a>, then create a cultural protocol in it.', [
        ':url' => $add_community->toString(),
      ]);
    }
    return $this->t('To add media, you need a role in a cultural protocol that allows adding media. Ask a community manager or protocol steward for access.');
  }

  /**
   * Returns the message as a warning to place on a page.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The person trying to add media.
   *
   * @return array
   *   A render array.
   */
  public function build(AccountInterface $account): array {
    return [
      '#theme' => 'status_messages',
      '#message_list' => ['warning' => [$this->getMessage($account)]],
      '#status_headings' => ['warning' => $this->t('Warning message')],
      '#cache' => [
        'contexts' => ['user'],
        'tags' => ['protocol_list', 'og_membership_list'],
      ],
    ];
  }

  /**
   * Checks whether any cultural protocol exists on the site.
   */
  protected function siteHasProtocols(): bool {
    return (bool) $this->entityTypeManager->getStorage('protocol')->getQuery()
      ->accessCheck(FALSE)
      ->range(0, 1)
      ->count()
      ->execute();
  }

}
