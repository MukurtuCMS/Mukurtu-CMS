<?php

namespace Drupal\mukurtu_protocol\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Replaces genpass's UserSetRandomPassword to also send a one-time login email.
 */
#[Action(
  id: 'genpass_set_random_password',
  label: new TranslatableMarkup('Set new random password'),
  type: 'user'
)]
class MukurtuUserSetRandomPasswordAction extends ActionBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected PasswordGeneratorInterface $passwordGenerator,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('password_generator'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @return void
   */
  public function execute($account = NULL): void {
    if ($account !== FALSE && $account !== NULL) {
      $account->original = clone $account;
      $account->setPassword($this->passwordGenerator->generate());
      $account->save();

      $sent = _user_mail_notify('password_reset', $account);

      $this->messenger()->addStatus($this->t(
        $sent
          ? 'A password reset email has been sent to %name.'
          : 'The password for %name has been reset.',
        ['%name' => $account->getDisplayName()]
      ));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    $access = $object->status->access('edit', $account, TRUE)
      ->andIf($object->access('update', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
