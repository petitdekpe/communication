<?php

declare(strict_types=1);

namespace App\Twig\Components\Layout;

use App\Menu\MenuItem;
use App\Menu\MenuProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class MainNav
{
    public function __construct(private readonly MenuProvider $menu)
    {
    }

    /** @return list<MenuItem> */
    public function getItems(): array
    {
        return $this->menu->main();
    }
}
