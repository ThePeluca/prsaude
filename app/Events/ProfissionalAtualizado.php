<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Profissional;
use Illuminate\Foundation\Events\Dispatchable;

/** Emitido mesmo sem ouvinte, de propósito (doc §7.7). */
final class ProfissionalAtualizado
{
    use Dispatchable;

    public function __construct(public readonly Profissional $profissional) {}
}
