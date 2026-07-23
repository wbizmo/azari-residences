<?php

namespace App\View\Components\PublicSite;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Layout extends Component
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public string $bodyClass = '',
    ) {}

    public function render(): View
    {
        return view('components.public.layout');
    }
}
