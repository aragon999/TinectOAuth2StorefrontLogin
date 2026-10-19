<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Exception;

class OAuthEnforcedProviderException extends OAuthException
{
    public function __construct()
    {
        parent::__construct('The email domain of this account requires login via a different OAuth client.');
    }

    public function getSnippetKey(): string
    {
        return 'tinect-oauth.error.enforcedProvider';
    }
}
