<?php

declare(strict_types=1);

namespace App\Livewire\Printing;

use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PrinterSettings extends Component
{
    public function render(): View
    {
        return view('livewire.printing.printer-settings');
    }
}
