<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Tests\Unit\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tinect\OAuth2StorefrontLogin\Service\EnforcedEmailDomainService;

final class EnforcedEmailDomainServiceTest extends TestCase
{
    public static function domainProvider(): iterable
    {
        yield 'plain' => ['max@example.com', 'example.com'];
        yield 'uppercase' => ['Max@Example.COM', 'example.com'];
        yield 'whitespace' => [' max@example.com ', 'example.com'];
        yield 'last at sign wins' => ['"a@b"@example.com', 'example.com'];
        yield 'no at sign' => ['example.com', null];
        yield 'empty domain' => ['max@', null];
    }

    #[DataProvider('domainProvider')]
    public function testExtractDomain(string $email, ?string $expected): void
    {
        static::assertSame($expected, EnforcedEmailDomainService::extractDomain($email));
    }

    public function testNormalizeDomainStripsAtSignAndLowercases(): void
    {
        static::assertSame('example.com', EnforcedEmailDomainService::normalizeDomain(' @Example.com '));
    }

    public function testReturnsEnforcedClientIdsForMatchingDomain(): void
    {
        $service = $this->makeService([
            ['id' => 'client-a', 'enforced_email_domains' => '["Example.com", "@other.org"]'],
            ['id' => 'client-b', 'enforced_email_domains' => '["example.com"]'],
        ]);

        static::assertTrue($service->hasEnforcedDomains());
        static::assertSame(['client-a', 'client-b'], $service->getEnforcedClientIds('max@EXAMPLE.com'));
        static::assertSame(['client-a'], $service->getEnforcedClientIds('max@other.org'));
    }

    public function testSubdomainsAreNotMatched(): void
    {
        $service = $this->makeService([
            ['id' => 'client-a', 'enforced_email_domains' => '["example.com"]'],
        ]);

        static::assertSame([], $service->getEnforcedClientIds('max@shop.example.com'));
    }

    public function testIgnoresInvalidAndEmptyValues(): void
    {
        $service = $this->makeService([
            ['id' => 'client-a', 'enforced_email_domains' => 'not-json'],
            ['id' => 'client-b', 'enforced_email_domains' => '["", 42, null]'],
        ]);

        static::assertFalse($service->hasEnforcedDomains());
        static::assertSame([], $service->getEnforcedClientIds('max@example.com'));
    }

    public function testQueriesDatabaseOnlyOnceUntilReset(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::exactly(2))->method('fetchAllAssociative')->willReturn([]);

        $service = new EnforcedEmailDomainService($connection);
        $service->getEnforcedClientIds('max@example.com');
        $service->hasEnforcedDomains();
        $service->reset();
        $service->hasEnforcedDomains();
    }

    /**
     * @param list<array{id: string, enforced_email_domains: string}> $rows
     */
    private function makeService(array $rows): EnforcedEmailDomainService
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn($rows);

        return new EnforcedEmailDomainService($connection);
    }
}
