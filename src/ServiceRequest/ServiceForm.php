<?php

declare(strict_types=1);

namespace App\ServiceRequest;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Formulaire interne d'une démarche en ligne. Une démarche (Service) désigne le sien par sa clé (formKey) ;
 * ajouter une démarche en ligne revient à écrire une classe qui implémente cette interface (voir Accreditation\).
 */
#[AutoconfigureTag('app.service_form')]
interface ServiceForm
{
    /** Clé stockée dans Service::$formKey. */
    public function key(): string;

    /**
     * @return class-string<FormTypeInterface<mixed>>
     */
    public function formType(): string;

    public function createData(): RequestData;
}
