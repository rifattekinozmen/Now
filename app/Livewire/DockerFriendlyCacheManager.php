<?php

namespace App\Livewire;

use Livewire\Compiler\CacheManager;

/**
 * Docker Desktop + Windows/WSL bind mount üzerinde kaynak dosyalarda `touch()` / utime
 * reddedilebiliyor; Livewire derleyicisi bu durumda log spam üretir. Başarısız olursa yutulur.
 */
class DockerFriendlyCacheManager extends CacheManager
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
