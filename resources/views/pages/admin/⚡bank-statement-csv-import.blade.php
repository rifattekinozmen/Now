<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\BankStatementCsvImport;
use App\Services\Finance\BankStatementOcrService;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

new #[Title('Bank statement CSV import')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;

    public mixed $csvFile = null;

    public mixed $pdfFile = null;

    /** @var list<array{booked_at: string|null, amount: string|null, description: string|null}> */
    public array $previewRows = [];

    public ?int $savedImportId = null;

    public function mount(): void
    {
        $this->ensureLogisticsAdmin();
        Gate::authorize('create', BankStatementCsvImport::class);
    }

    public function importCsv(BankStatementOcrService $parser): void
    {
        $this->ensureLogisticsAdmin();
        Gate::authorize('create', BankStatementCsvImport::class);

        $this->validate([
            'csvFile' => ['required', 'file', 'max:512', 'mimes:csv,txt'],
        ]);

        $content = $this->csvFile->get();
        if (! is_string($content)) {
            $content = '';
        }

        $rows = $parser->extractRowsFromCsvContent($content);
        $this->previewRows = $rows;
        $this->savedImportId = null;

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $import = BankStatementCsvImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => $this->csvFile->getClientOriginalName(),
            'row_count' => count($rows),
            'rows' => $rows,
        ]);

        $this->savedImportId = $import->id;
        $this->reset('csvFile');
    }

    public function importPdf(BankStatementOcrService $parser): void
    {
        $this->ensureLogisticsAdmin();
        Gate::authorize('create', BankStatementCsvImport::class);

        $this->validate([
            'pdfFile' => ['required', 'file', 'max:10240', 'mimes:pdf'],
        ]);

        $path = $this->pdfFile->getRealPath();
        if ($path === false || $path === '') {
            $this->addError('pdfFile', __('Could not read the uploaded file.'));

            return;
        }

        $rows = $parser->extractRowsFromPdf($path);
        if ($rows === []) {
            $this->previewRows = [];
            $this->savedImportId = null;
            $this->addError(
                'pdfFile',
                __('No transaction lines were parsed from this PDF. Try a CSV export from your bank, or use a PDF with a selectable text layer (image-only scans need separate OCR).')
            );

            return;
        }

        $this->previewRows = $rows;
        $this->savedImportId = null;

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $import = BankStatementCsvImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => $this->pdfFile->getClientOriginalName(),
            'row_count' => count($rows),
            'rows' => $rows,
        ]);

        $this->savedImportId = $import->id;
        $this->reset('pdfFile');
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <flux:heading size="xl">{{ __('Bank statement CSV import') }}</flux:heading>
        <div class="flex flex-wrap gap-2">
            <flux:button :href="route('admin.finance.index')" variant="ghost" wire:navigate>{{ __('Back to finance summary') }}</flux:button>
        </div>
    </div>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational import only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Parsed rows are not accounting entries. PDF uses text layer extraction; scanned images need separate OCR.') }}
        </flux:callout.text>
    </flux:callout>

    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-2">{{ __('Upload CSV') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Expected columns: date (YYYY-MM-DD or DD.MM.YYYY), amount, description. Turkish or English headers.') }}
        </flux:text>
        <form wire:submit="importCsv" class="flex flex-col gap-4">
            <input type="file" wire:model="csvFile" accept=".csv,.txt,text/csv" class="text-sm" />
            @error('csvFile')
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary">{{ __('Parse and save preview') }}</flux:button>
        </form>
    </flux:card>

    <flux:card class="!p-4">
        <flux:heading size="lg" class="mb-2">{{ __('Upload PDF (text layer)') }}</flux:heading>
        <flux:text class="mb-4 text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Each line should start with a date (YYYY-MM-DD or DD.MM.YYYY), include a description, and end with an amount.') }}
        </flux:text>
        <form wire:submit="importPdf" class="flex flex-col gap-4">
            <input type="file" wire:model="pdfFile" accept=".pdf,application/pdf" class="text-sm" />
            @error('pdfFile')
                <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary">{{ __('Parse PDF and save preview') }}</flux:button>
        </form>
    </flux:card>

    @if ($savedImportId !== null)
        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Import #:id saved with :count rows.', ['id' => $savedImportId, 'count' => count($previewRows)]) }}
        </flux:text>
    @endif

    @if (count($previewRows) > 0)
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Preview') }}</flux:heading>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($previewRows as $idx => $row)
                        <flux:table.row :key="$idx">
                            <flux:table.cell>{{ $row['booked_at'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row['amount'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row['description'] ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
