<?php

namespace Drupal\genpass\Plugin\Validation\Constraint;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\TypedData\PrimitiveInterface;
use Drupal\Core\TypedData\Validation\TypedDataAwareValidatorTrait;
use Drupal\genpass\GenpassInterface;
use Drupal\genpass\ValidationInterlockHelperInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates a GenpassModeConstraint.
 */
class GenpassModeConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  use AutowireTrait;
  use TypedDataAwareValidatorTrait;

  /**
   * Constructs a new GenpassModeConstraintValidator object.
   */
  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ValidationInterlockHelperInterface $validationInterlock,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    switch ($constraint->operationMode) {
      case 'verify_mail':
        $this->storeFormValue($value, $constraint);
        break;

      case 'genpass_mode':
        $this->validateGenpassMode($value, $constraint);
        break;

      default:
        throw new \InvalidArgumentException('Unknown operation mode:' . $constraint->operationMode);
    }
  }

  /**
   * Validate the genpass_mode interlocked with verify_mail value.
   *
   * @param mixed $value
   *   Input value to validate.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint being validated.
   */
  protected function validateGenpassMode($value, Constraint $constraint): void {

    // Get the value with the proper datatype.
    $typed_data = $this->getTypedData();
    if (!($typed_data instanceof PrimitiveInterface)) {
      throw new \LogicException('The data type must be a PrimitiveInterface at this point.');
    }
    $genpass_mode = $typed_data->getCastedValue();

    // Get the user settings. This value needs to be obtained from the
    // submitted value if submitted during form, or from settings if not.
    $user_email_verification = $this->validationInterlock->getVerifyMail();
    if (is_null($user_email_verification)) {
      $user_settings = $this->configFactory->get('user.settings');
      $user_email_verification = $user_settings->get('verify_mail');
    }

    // Email verification can only combine with a user not being able to enter
    // a password.
    $user_can_enter_password = in_array($genpass_mode, [
      GenpassInterface::PASSWORD_REQUIRED,
      GenpassInterface::PASSWORD_OPTIONAL,
    ]);
    if ($user_email_verification && $user_can_enter_password) {
      $this->context->addViolation($constraint->incompatibleSettings, [
        '%chosen' => ($genpass_mode == GenpassInterface::PASSWORD_REQUIRED)
          ? 'Users must enter a password on registration'
          : 'Users may enter a password on registration',
      ]);
    }
  }

  /**
   * Store the value of user.settings:verify_mail for use in validation.
   *
   * @param mixed $value
   *   The value to store for later use.
   * @param \Symfony\Component\Validator\Constraint $constraint
   *   The constraint being validated.
   */
  protected function storeFormValue($value, Constraint $constraint): void {
    $this->validationInterlock->setVerifyMail($value);
  }

}
