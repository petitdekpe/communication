<?php

declare(strict_types=1);

namespace App\ServiceRequest\Accreditation;

use App\ServiceRequest\AbstractRequestData;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Demande d'accréditation presse : identité, organe de presse, fonction, événement visé.
 */
final class AccreditationData extends AbstractRequestData
{
    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 100)]
    public ?string $lastName = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 100)]
    public ?string $firstName = null;

    #[Assert\Length(max: 30)]
    #[Assert\Regex(pattern: '/^[0-9+().\s-]*$/', message: 'request.error.phone_invalid')]
    public ?string $phone = null;

    /** Organe de presse. */
    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 150)]
    public ?string $mediaOutlet = null;

    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 100)]
    public ?string $jobTitle = null;

    /** Événement pour lequel l'accréditation est demandée. */
    #[Assert\NotBlank(message: 'request.error.required', normalizer: 'trim')]
    #[Assert\Length(max: 200)]
    public ?string $eventName = null;

    public function toPayload(): array
    {
        return [
            'lastName' => trim((string) $this->lastName),
            'firstName' => trim((string) $this->firstName),
            'phone' => '' === trim((string) $this->phone) ? null : trim((string) $this->phone),
            'mediaOutlet' => trim((string) $this->mediaOutlet),
            'jobTitle' => trim((string) $this->jobTitle),
            'eventName' => trim((string) $this->eventName),
        ];
    }
}
