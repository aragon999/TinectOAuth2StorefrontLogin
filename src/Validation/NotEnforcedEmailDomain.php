<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Validation;

use Symfony\Component\Validator\Constraint;

class NotEnforcedEmailDomain extends Constraint
{
    final public const CODE = 'TINECT_OAUTH_ENFORCED_EMAIL_DOMAIN';

    protected const ERROR_NAMES = [
        self::CODE => 'TINECT_OAUTH_ENFORCED_EMAIL_DOMAIN',
    ];

    public string $message = 'Accounts with the email domain {{ domain }} must sign in via the shop login page.';
}
