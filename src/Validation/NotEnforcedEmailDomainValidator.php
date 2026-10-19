<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Validation;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Tinect\OAuth2StorefrontLogin\Service\EnforcedEmailDomainService;

class NotEnforcedEmailDomainValidator extends ConstraintValidator
{
    public function __construct(
        private readonly EnforcedEmailDomainService $enforcedEmailDomains,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof NotEnforcedEmailDomain) {
            throw new UnexpectedTypeException($constraint, NotEnforcedEmailDomain::class);
        }

        if (!\is_string($value) || $this->enforcedEmailDomains->getEnforcedClientIds($value) === []) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ domain }}', (string) EnforcedEmailDomainService::extractDomain($value))
            ->setCode(NotEnforcedEmailDomain::CODE)
            ->addViolation();
    }
}
