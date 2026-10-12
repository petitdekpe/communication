<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class ServiceFormRegistry
{
    /** @var array<string, ServiceForm> */
    private array $forms = [];

    /**
     * @param iterable<ServiceForm> $forms
     */
    public function __construct(
        #[AutowireIterator('app.service_form')]
        iterable $forms,
    ) {
        foreach ($forms as $form) {
            $this->forms[$form->key()] = $form;
        }
    }

    public function get(?string $key): ?ServiceForm
    {
        return null === $key ? null : ($this->forms[$key] ?? null);
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->forms);
    }
}
