<?php

namespace App\View\Components\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Layout extends Component
{
    public function __construct(public ?string $title = null) {}

    public function render(): View
    {
        return view('admin.layout');
    }
}
