<?php declare(strict_types=1);

namespace Tinect\OAuth2StorefrontLogin\Subscriber;

use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Tinect\OAuth2StorefrontLogin\Service\CustomerResolver;
use Tinect\OAuth2StorefrontLogin\Service\EnforcedEmailDomainService;
use Tinect\OAuth2StorefrontLogin\Validation\NotEnforcedEmailDomain;

/**
 * Forces customers whose email domain is enforced by an OAuth client to use that client:
 * storefront login, registration and password recovery are redirected to the provider,
 * Store API requests and email changes are rejected via validation.
 */
final readonly class EnforcedEmailDomainSubscriber implements EventSubscriberInterface
{
    /**
     * Storefront POST routes => path of the email field in the request body.
     */
    private const REDIRECT_ROUTES = [
        'frontend.account.login' => ['username'],
        'frontend.account.register.save' => ['email'],
        'frontend.account.recover.request' => ['email'],
    ];

    public function __construct(
        private EnforcedEmailDomainService $enforcedEmailDomains,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::CONTROLLER => 'onController',
            'framework.validation.customer.create' => 'addEmailConstraint',
            'framework.validation.customer.email.update' => 'addEmailConstraint',
            'framework.validation.customer.email.recover' => 'addEmailConstraint',
        ];
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest() || !$request->isMethod(Request::METHOD_POST)) {
            return;
        }

        $fieldPath = self::REDIRECT_ROUTES[$request->attributes->getString('_route')] ?? null;

        if ($fieldPath === null) {
            return;
        }

        $email = $this->getEmail($request, $fieldPath);

        if ($email === null) {
            return;
        }

        $clientIds = $this->enforcedEmailDomains->getEnforcedClientIds($email);

        if ($clientIds === []) {
            return;
        }

        $parameters = ['clientId' => $clientIds[0]];
        foreach (['redirectTo', 'redirectParameters'] as $key) {
            $value = $request->request->get($key);
            if (\is_string($value) && $value !== '') {
                $parameters[$key] = $value;
            }
        }

        $url = $this->urlGenerator->generate('widgets.tinect.oauth.redirect', $parameters);

        $event->setController(static fn () => new RedirectResponse($url));
    }

    public function addEmailConstraint(BuildValidationEvent $event): void
    {
        // Customers registered or updated by the OAuth flow itself are allowed
        if ($event->getContext()->hasState(CustomerResolver::STATE)) {
            return;
        }

        $event->getDefinition()->add('email', new NotEnforcedEmailDomain());
    }

    /**
     * @param list<string> $fieldPath
     */
    private function getEmail(Request $request, array $fieldPath): ?string
    {
        $value = $request->request->all();

        foreach ($fieldPath as $key) {
            if (!\is_array($value) || !\array_key_exists($key, $value)) {
                return null;
            }

            $value = $value[$key];
        }

        return \is_string($value) ? trim($value) : null;
    }
}
