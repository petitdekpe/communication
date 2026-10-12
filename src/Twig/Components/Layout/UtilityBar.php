<?php

declare(strict_types=1);

namespace App\Twig\Components\Layout;

use App\Menu\MenuItem;
use App\Menu\MenuProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class UtilityBar
{
    public function __construct(private readonly MenuProvider $menu)
    {
    }

    /** @return list<MenuItem> */
    public function getLinks(): array
    {
        return $this->menu->utility();
    }
}
