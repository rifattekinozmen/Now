<?php

namespace App\Livewire;

use Livewire\Compiler\CacheManager;

/**
 * Windows (ve bazı bind mount) ortamlarında kaynak dosyalarda `touch()` / utime
 * reddedilebiliyor; Livewire derleyicisi bu durumda log spam üretir. Başarısız olursa yutulur.
 */
class WindowsFriendlyLivewireCacheManager extends CacheManager
{
    public function mutateFileModificationTime(string $path): void
    {
        $original = @filemtime($path);
        if ($original === false) {
            return;
        }

        @touch($path, $original - 1);
    }
}
