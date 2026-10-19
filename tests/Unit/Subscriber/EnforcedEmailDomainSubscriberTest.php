<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Tests\Unit\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tinect\OAuth2StorefrontLogin\Service\CustomerResolver;
use Tinect\OAuth2StorefrontLogin\Service\EnforcedEmailDomainService;
use Tinect\OAuth2StorefrontLogin\Subscriber\EnforcedEmailDomainSubscriber;
use Tinect\OAuth2StorefrontLogin\Validation\NotEnforcedEmailDomain;

final class EnforcedEmailDomainSubscriberTest extends TestCase
{
    public function testRedirectsLoginWithEnforcedDomainToProvider(): void
    {
        $request = $this->makeRequest('frontend.account.login', [
            'username' => 'Max@Example.com',
            'password' => 'irrelevant',
            'redirectTo' => 'frontend.checkout.confirm.page',
            'redirectParameters' => '{"foo":"bar"}',
        ]);

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->expects(self::once())
            ->method('generate')
            ->with('widgets.tinect.oauth.redirect', [
                'clientId' => 'client-a',
                'redirectTo' => 'frontend.checkout.confirm.page',
                'redirectParameters' => '{"foo":"bar"}',
            ])
            ->willReturn('/account/oauth/client-a?redirectTo=frontend.checkout.confirm.page');

        $event = $this->makeControllerEvent($request);
        $this->makeSubscriber($urlGenerator)->onController($event);

        $response = ($event->getController())();
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/oauth/client-a?redirectTo=frontend.checkout.confirm.page', $response->getTargetUrl());
    }

    public function testRedirectsRegistrationAndRecoveryWithEnforcedDomain(): void
    {
        foreach ([
            'frontend.account.register.save' => ['email' => 'max@example.com'],
            'frontend.account.recover.request' => ['email' => ['email' => 'max@example.com']],
        ] as $route => $body) {
            $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
            $urlGenerator->method('generate')->willReturn('/account/oauth/client-a');

            $event = $this->makeControllerEvent($this->makeRequest($route, $body));
            $this->makeSubscriber($urlGenerator)->onController($event);

            static::assertInstanceOf(RedirectResponse::class, ($event->getController())(), $route);
        }
    }

    public function testKeepsControllerForOtherDomainsRoutesAndMethods(): void
    {
        $cases = [
            $this->makeRequest('frontend.account.login', ['username' => 'max@other.org']),
            $this->makeRequest('frontend.account.login', []),
            $this->makeRequest('frontend.account.profile.save', ['email' => 'max@example.com']),
            $this->makeRequest('frontend.account.login', ['username' => 'max@example.com'], Request::METHOD_GET),
        ];

        foreach ($cases as $request) {
            $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
            $urlGenerator->expects(self::never())->method('generate');

            $event = $this->makeControllerEvent($request);
            $controller = $event->getController();
            $this->makeSubscriber($urlGenerator)->onController($event);

            static::assertSame($controller, $event->getController());
        }
    }

    public function testAddsEmailConstraint(): void
    {
        $definition = new DataValidationDefinition('customer.create');
        $event = new BuildValidationEvent($definition, new DataBag(), Context::createDefaultContext());

        $this->makeSubscriber($this->createStub(UrlGeneratorInterface::class))->addEmailConstraint($event);

        static::assertInstanceOf(NotEnforcedEmailDomain::class, $definition->getProperty('email')[0] ?? null);
    }

    public function testSkipsEmailConstraintDuringOAuthFlow(): void
    {
        $context = Context::createDefaultContext();
        $context->addState(CustomerResolver::STATE);

        $definition = new DataValidationDefinition('customer.create');
        $event = new BuildValidationEvent($definition, new DataBag(), $context);

        $this->makeSubscriber($this->createStub(UrlGeneratorInterface::class))->addEmailConstraint($event);

        static::assertSame([], $definition->getProperty('email'));
    }

    private function makeSubscriber(UrlGeneratorInterface $urlGenerator): EnforcedEmailDomainSubscriber
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            ['id' => 'client-a', 'enforced_email_domains' => '["example.com"]'],
        ]);

        return new EnforcedEmailDomainSubscriber(new EnforcedEmailDomainService($connection), $urlGenerator);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function makeRequest(string $route, array $body, string $method = Request::METHOD_POST): Request
    {
        $request = new Request([], $body);
        $request->setMethod($method);
        $request->attributes->set('_route', $route);

        return $request;
    }

    private function makeControllerEvent(Request $request): ControllerEvent
    {
        return new ControllerEvent(
            $this->createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
