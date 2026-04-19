<?php

namespace App\Http\Controllers\Admin;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadDocumentController
{
    public function __invoke(Request $request, Document $document): StreamedResponse
    {
        Gate::authorize('view', $document);

        abort_if(! $document->file_path, 404);
        abort_if(! Storage::disk('local')->exists($document->file_path), 404);

        $filename = $document->title.'.'.pathinfo($document->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download($document->file_path, $filename);
    }
}
