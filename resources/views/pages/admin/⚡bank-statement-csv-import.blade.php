<?php

use App\Livewire\Concerns\RequiresLogisticsAdmin;
use App\Models\BankStatementCsvImport;
use App\Models\ChartAccount;
use App\Models\JournalEntry;
use App\Services\Finance\BankStatementJournalPoster;
use App\Services\Finance\BankStatementOcrService;
use App\Services\Finance\BankStatementRowMatcher;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\WithFileUploads;

new #[Title('Bank statement CSV import')] class extends Component
{
    use RequiresLogisticsAdmin;
    use WithFileUploads;

    public mixed $csvFile = null;

    public mixed $pdfFile = null;

    /**
     * @var list<array<string, mixed>>
     */
    public array $previewRows = [];

    public ?int $savedImportId = null;

    /** @var array<int|string, int> */
    public array $rowCustomerSelections = [];

    public ?int $bankChartAccountId = null;

    public ?int $accountsReceivableChartAccountId = null;

    public function mount(): void
    {
        $this->ensureLogisticsAdmin();
        Gate::authorize('create', BankStatementCsvImport::class);
    }

    public function importCsv(BankStatementOcrService $parser, BankStatementRowMatcher $matcher): void
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
        $tenantId = auth()->user()?->tenant_id;
        $this->previewRows = is_int($tenantId)
            ? $matcher->enrichRowsForTenant($tenantId, $rows)
            : $rows;
        $this->savedImportId = null;

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        $import = BankStatementCsvImport::query()->create([
            'user_id' => $user->id,
            'original_filename' => $this->csvFile->getClientOriginalName(),
            'row_count' => count($this->previewRows),
            'rows' => $this->previewRows,
        ]);

        $this->savedImportId = $import->id;
        $this->previewRows = $import->rows ?? [];
        $this->syncRowSelectionsFromPreview();
        $this->reset('csvFile');
    }

    public function importPdf(BankStatementOcrService $parser, BankStatementRowMatcher $matcher): void
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

        $parsed = $parser->extractRowsFromPdfWithDiagnostics($path);
        $rawRows = $parsed['rows'];
        if ($rawRows === []) {
            $this->previewRows = [];
            $this->savedImportId = null;
            $this->addError('pdfFile', $parser->pdfImportDiagnosticMessage($parsed['diagnostic']));

            return;
        }

        $tenantId = auth()->user()?->tenant_id;
        $rows = is_int($tenantId)
            ? $matcher->enrichRowsForTenant($tenantId, $rawRows)
            : $rawRows;
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
        $this->previewRows = $import->rows ?? [];
        $this->syncRowSelectionsFromPreview();
        $this->reset('pdfFile');
    }

    public function postJournalRow(int $index, BankStatementJournalPoster $poster): void
    {
        $this->ensureLogisticsAdmin();
        Gate::authorize('create', JournalEntry::class);

        if ($this->savedImportId === null) {
            $this->addError('posting', __('Save an import first.'));

            return;
        }

        $this->validate([
            'bankChartAccountId' => ['required', 'integer'],
            'accountsReceivableChartAccountId' => ['required', 'integer'],
        ]);

        /** @var BankStatementCsvImport $import */
        $import = BankStatementCsvImport::query()->findOrFail($this->savedImportId);

        $raw = $this->rowCustomerSelections[$index] ?? null;
        $customerId = is_numeric($raw) ? (int) $raw : null;
        if ($customerId === null || $customerId < 1) {
            $this->addError('row_'.$index, __('Choose a customer for this row.'));

            return;
        }

        try {
            $poster->postMatchedRow(
                $import,
                $index,
                (int) $this->bankChartAccountId,
                (int) $this->accountsReceivableChartAccountId,
                $customerId,
                auth()->id(),
            );
        } catch (\Throwable $e) {
            $this->addError('posting', $e->getMessage());

            return;
        }

        $import->refresh();
        $this->previewRows = is_array($import->rows) ? $import->rows : [];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, ChartAccount>
     */
    #[Computed]
    public function chartAccounts(): \Illuminate\Database\Eloquent\Collection
    {
        return ChartAccount::query()->orderBy('code')->get();
    }

    private function syncRowSelectionsFromPreview(): void
    {
        $this->rowCustomerSelections = [];
        foreach ($this->previewRows as $idx => $row) {
            if (! empty($row['match_candidates'][0]['customer_id'])) {
                $this->rowCustomerSelections[$idx] = (int) $row['match_candidates'][0]['customer_id'];
            }
        }
    }
}; ?>

<div class="mx-auto flex w-full max-w-6xl flex-col gap-6 p-4 lg:p-8">
    <x-admin.page-header :heading="__('Bank statement CSV import')">
        <x-slot name="actions">
            <flux:button :href="route('admin.finance.index')" variant="outline" wire:navigate>{{ __('Back to finance summary') }}</flux:button>
        </x-slot>
    </x-admin.page-header>

    <flux:callout variant="warning" icon="exclamation-triangle">
        <flux:callout.heading>{{ __('Operational import only') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('Parsed rows are stored for review. Optional: post a balanced journal entry per row after choosing bank and receivable accounts. PDF uses text layer extraction; scanned images need separate OCR.') }}
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

    @error('posting')
        <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror

    @if (count($previewRows) > 0)
        <flux:card>
            <flux:heading size="lg" class="mb-4">{{ __('Preview') }}</flux:heading>

            @can('create', App\Models\JournalEntry::class)
                @if ($this->chartAccounts->isEmpty())
                    <flux:callout variant="warning" class="mb-4">
                        <flux:callout.text>
                            {{ __('Add at least two chart accounts (e.g. bank and accounts receivable) under Chart of accounts before posting.') }}
                            <flux:link :href="route('admin.finance.chart-accounts.index')" wire:navigate class="ms-1">{{ __('Open chart of accounts') }}</flux:link>
                        </flux:callout.text>
                    </flux:callout>
                @else
                    <div class="mb-6 grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model.live="bankChartAccountId" :label="__('Bank / cash account (debit on inflow)')">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($this->chartAccounts as $acct)
                                <flux:select.option :value="$acct->id">{{ $acct->code }} — {{ $acct->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model.live="accountsReceivableChartAccountId" :label="__('Accounts receivable (paired side)')">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($this->chartAccounts as $acct)
                                <flux:select.option :value="$acct->id">{{ $acct->code }} — {{ $acct->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                @endif
            @endcan

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    <flux:table.column>{{ __('Amount') }}</flux:table.column>
                    <flux:table.column>{{ __('Description') }}</flux:table.column>
                    <flux:table.column>{{ __('Suggested matches') }}</flux:table.column>
                    <flux:table.column>{{ __('Customer for posting') }}</flux:table.column>
                    <flux:table.column>{{ __('Posted') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($previewRows as $idx => $row)
                        <flux:table.row :key="'br-'.$idx">
                            <flux:table.cell>{{ $row['booked_at'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row['amount'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $row['description'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="max-w-xs text-sm">
                                @if (! empty($row['match_candidates']))
                                    <ul class="list-inside list-disc space-y-1">
                                        @foreach ($row['match_candidates'] as $c)
                                            <li>
                                                #{{ $c['customer_id'] }}
                                                {{ $c['label'] }}
                                                ({{ $c['reason'] }}, {{ $c['score'] }})
                                            </li>
                                        @endforeach
                                    </ul>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="min-w-[10rem]">
                                @if (! empty($row['match_candidates']))
                                    <select
                                        class="w-full rounded-md border border-zinc-300 bg-white text-sm dark:border-zinc-600 dark:bg-zinc-900"
                                        wire:model.live="rowCustomerSelections.{{ $idx }}"
                                    >
                                        <option value="">{{ __('Choose…') }}</option>
                                        @foreach ($row['match_candidates'] as $c)
                                            <option value="{{ $c['customer_id'] }}">{{ $c['label'] }} (#{{ $c['customer_id'] }})</option>
                                        @endforeach
                                    </select>
                                    @error('row_'.$idx)
                                        <flux:text class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                                    @enderror
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @if (! empty($row['journal_entry_id']))
                                    <flux:link :href="route('admin.finance.journal-entries.index').'#entry-'.$row['journal_entry_id']" wire:navigate>
                                        #{{ $row['journal_entry_id'] }}
                                    </flux:link>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                @can('create', App\Models\JournalEntry::class)
                                    @if (! empty($row['match_candidates']) && empty($row['journal_entry_id']) && ! $this->chartAccounts->isEmpty())
                                        <flux:button size="sm" variant="primary" wire:click="postJournalRow({{ $idx }})">
                                            {{ __('Post') }}
                                        </flux:button>
                                    @endif
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
