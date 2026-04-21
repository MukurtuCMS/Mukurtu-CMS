<?php

namespace Drupal\redirect\Plugin\Validation\Constraint;

use Drupal\redirect\Entity\Redirect;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the uniqueness of a redirect based on its hash.
 */
class UniqueHashValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs a UniqueHashValidator object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($redirect, Constraint $constraint) {
    if (!$redirect instanceof Redirect) {
      throw new \InvalidArgumentException(
        'The "RedirectUniqueHash" constraint can only be used on redirect entities.',
      );
    }

    $existing_redirect = $this->getExistingRedirect($redirect);
    if ($existing_redirect === NULL) {
      return;
    }

    assert(property_exists($constraint, 'message'));
    assert(is_string($constraint->message));
    $this->context->addViolation(
      $constraint->message,
      $this->buildViolationParameters($redirect, $existing_redirect),
    );
  }

  /**
   * Returns the first existing redirect that matches the given one.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect being validated.
   *
   * @return \Drupal\redirect\Entity\Redirect|null
   *   The first existing redirect entity, or `NULL` if none was found.
   */
  protected function getExistingRedirect(Redirect $redirect): ?Redirect {
    $storage = $this->entityTypeManager->getStorage('redirect');
    $query = $storage->getQuery()->accessCheck(FALSE);

    $redirect_id = $redirect->id();
    if (isset($redirect_id)) {
      $id_key = $redirect->getEntityType()->getKey('id');
      $query->condition($id_key, $redirect_id, '<>');
    }

    $ids = $query
      ->condition('hash', $this->getHash($redirect))
      ->range(0, 1)
      ->execute();

    if (count($ids) === 0) {
      return NULL;
    }

    return $storage->load(current($ids));
  }

  /**
   * Returns the hash for the given redirect.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect being validated.
   *
   * @return string
   *   The hash for the given redirect.
   */
  protected function getHash(Redirect $redirect): string {
    $source = $redirect->getSource();

    return Redirect::generateHash(
      $source['path'] ?? '',
      $source['query'] ?? [],
      $redirect->get('language')->value,
    );
  }

  /**
   * Returns the parameters required by the constraint message.
   *
   * @param \Drupal\redirect\Entity\Redirect $redirect
   *   The redirect being validated.
   * @param \Drupal\redirect\Entity\Redirect $existing_redirect
   *   The redirect found as duplicate.
   *
   * @return array
   *   An associative array containing the parameters for building the
   *   constraint message.
   */
  protected function buildViolationParameters(
    Redirect $redirect,
    Redirect $existing_redirect,
  ): array {
    return [
      '%source' => ltrim($redirect->getSourcePathWithQuery(), '/'),
      '@edit-page' => $existing_redirect->toUrl('edit-form')->toString(),
    ];
  }

}
