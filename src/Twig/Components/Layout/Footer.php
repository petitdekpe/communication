<?php

declare(strict_types=1);

namespace App\Twig\Components\Layout;

use App\Menu\MenuItem;
use App\Menu\MenuProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

#[AsTwigComponent]
final class Footer
{
    public function __construct(private readonly MenuProvider $menu)
    {
    }

    /** @return list<MenuItem> */
    public function getColumns(): array
    {
        return $this->menu->footerColumns();
    }

    /** @return list<MenuItem> */
    public function getSocial(): array
    {
        return $this->menu->social();
    }

    /** @return list<MenuItem> */
    public function getInstitutional(): array
    {
        return $this->menu->institutional();
    }

    /** @return list<MenuItem> */
    public function getLegal(): array
    {
        return $this->menu->legal();
    }
}
