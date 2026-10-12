<?php

declare(strict_types=1);

namespace App\ServiceRequest\Accreditation;

use App\ServiceRequest\RequestData;
use App\ServiceRequest\ServiceForm;

final class AccreditationForm implements ServiceForm
{
    public const string KEY = 'accreditation';

    public function key(): string
    {
        return self::KEY;
    }

    public function formType(): string
    {
        return AccreditationFormType::class;
    }

    public function createData(): RequestData
    {
        return new AccreditationData();
    }
}
