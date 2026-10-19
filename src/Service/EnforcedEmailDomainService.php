<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Service;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Resolves which OAuth clients are enforced for the domain of an email address.
 * Customers with such an address must sign in via one of these clients.
 */
final class EnforcedEmailDomainService implements ResetInterface
{
    /**
     * @var array<string, list<string>>|null domain => client ids
     */
    private ?array $domainMap = null;

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function normalizeDomain(string $domain): string
    {
        return strtolower(ltrim(trim($domain), '@'));
    }

    public static function extractDomain(string $email): ?string
    {
        $position = strrpos($email, '@');

        if ($position === false) {
            return null;
        }

        $domain = self::normalizeDomain(substr($email, $position + 1));

        return $domain === '' ? null : $domain;
    }

    public function hasEnforcedDomains(): bool
    {
        return $this->getDomainMap() !== [];
    }

    /**
     * @return list<string> ids of active clients enforced for the email's domain, empty if not enforced
     */
    public function getEnforcedClientIds(string $email): array
    {
        $domain = self::extractDomain($email);

        if ($domain === null) {
            return [];
        }

        return $this->getDomainMap()[$domain] ?? [];
    }

    public function reset(): void
    {
        $this->domainMap = null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function getDomainMap(): array
    {
        if ($this->domainMap !== null) {
            return $this->domainMap;
        }

        $rows = $this->connection->fetchAllAssociative(
            'SELECT LOWER(HEX(id)) AS id, enforced_email_domains
             FROM tinect_oauth_storefront_client
             WHERE active = 1
               AND enforced_email_domains IS NOT NULL
             ORDER BY created_at ASC',
        );

        $domainMap = [];
        foreach ($rows as $row) {
            $domains = json_decode((string) $row['enforced_email_domains'], true);

            if (!\is_array($domains)) {
                continue;
            }

            foreach ($domains as $domain) {
                if (!\is_string($domain)) {
                    continue;
                }

                $domain = self::normalizeDomain($domain);

                if ($domain === '') {
                    continue;
                }

                $domainMap[$domain][] = (string) $row['id'];
            }
        }

        return $this->domainMap = $domainMap;
    }
}
