<?php

namespace Drupal\mukurtu_import\Exception;

use Drupal\migrate\Exception\EntityValidationException;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Drupal\node\NodeInterface;
use Drupal\media\MediaInterface;
use Drupal\mukurtu_protocol\Entity\CommunityInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolControlInterface;
use Drupal\mukurtu_protocol\Entity\ProtocolInterface;

class MukurtuEntityValidationException extends EntityValidationException
{
  public function getViolationMessages()
  {
    $messages = [];

    foreach ($this->violations as $violation) {
      assert($violation instanceof ConstraintViolationInterface);
      $entity = $violation->getRoot()->getEntity();

      if ($entity instanceof MediaInterface) {
        $messages[] = sprintf('%s: Invalid value detected for field %s.',
          $entity->label(),
          $violation->getInvalidValue()->getFieldDefinition()->getLabel());
      }
      elseif ($entity instanceof NodeInterface) {
        $messages[] = sprintf('%s: Invalid value detected for field %s.',
          $entity->getTitle(),
          $violation->getInvalidValue()->getFieldDefinition()->getLabel());
      }
      elseif ($entity instanceof CommunityInterface) {
        // todo
      }
      elseif ($entity instanceof ProtocolInterface) {
        // todo
      }
      elseif ($entity instanceof ProtocolControlInterface) {
        // todo
      }
      else {
        // The cryptic message will suffice.
        $messages[] = sprintf('%s=%s', $violation->getPropertyPath(), $violation->getMessage());
      }
    }

    return $messages;
  }
}
