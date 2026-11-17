<?php

namespace App\Livewire\Concerns;

use App\Support\Trash;
use Illuminate\Support\Str;

/**
 * After something is moved to the trash, show a short "Undo" notice.
 * Pair with <x-undo-toast :label="$undoLabel" :key="$undoType.$undoId" />.
 */
trait OffersUndo
{
    public ?string $undoType = null;

    public ?int $undoId = null;

    public ?string $undoLabel = null;

    protected function offerUndo(string $type, int $id, string $label): void
    {
        $this->undoType = $type;
        $this->undoId = $id;
        $this->undoLabel = '“'.Str::limit($label, 40).'”';
    }

    public function undoTrash(): void
    {
        if ($this->undoType && $this->undoId) {
            Trash::query($this->undoType)?->whereKey($this->undoId)->first()?->restore();
        }

        $this->dismissUndo();
    }

    public function dismissUndo(): void
    {
        $this->reset('undoType', 'undoId', 'undoLabel');
    }
}
