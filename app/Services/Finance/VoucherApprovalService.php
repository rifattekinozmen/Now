<?php

namespace App\Services\Finance;

use App\Enums\VoucherStatus;
use App\Enums\VoucherType;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class VoucherApprovalService
{
    /**
     * Approve a pending voucher (Checker step).
     *
     * Updates the related CashRegister balance atomically.
     *
     * @throws RuntimeException if the voucher is not pending or balance would go negative.
     */
    public function approve(Voucher $voucher, User $approver): void
    {
        if (! $voucher->status->isPending()) {
            throw new RuntimeException("Voucher #{$voucher->id} is not in pending status.");
        }

        DB::transaction(function () use ($voucher, $approver): void {
            $cashRegister = $voucher->cashRegister()->lockForUpdate()->first();

            if ($cashRegister === null) {
                throw new RuntimeException("CashRegister not found for Voucher #{$voucher->id}.");
            }

            $newBalance = match ($voucher->type) {
                VoucherType::Income   => $cashRegister->current_balance + $voucher->amount,
                VoucherType::Expense  => $cashRegister->current_balance - $voucher->amount,
                VoucherType::Transfer => $cashRegister->current_balance, // handled separately
            };

            if ($newBalance < 0) {
                throw new RuntimeException(
                    "Insufficient balance in cash register '{$cashRegister->name}'. ".
                    "Current: {$cashRegister->current_balance}, Required: {$voucher->amount}"
                );
            }

            $cashRegister->update(['current_balance' => $newBalance]);

            $voucher->update([
                'status'      => VoucherStatus::Approved->value,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ]);

            Log::info('Voucher approved', [
                'voucher_id'         => $voucher->id,
                'tenant_id'          => $voucher->tenant_id,
                'type'               => $voucher->type->value,
                'amount'             => $voucher->amount,
                'approved_by'        => $approver->id,
                'cash_register_id'   => $cashRegister->id,
                'new_balance'        => $newBalance,
            ]);
        });
    }

    /**
     * Reject a pending voucher (Checker step).
     */
    public function reject(Voucher $voucher, User $rejector, string $reason = ''): void
    {
        if (! $voucher->status->isPending()) {
            throw new RuntimeException("Voucher #{$voucher->id} is not in pending status.");
        }

        $voucher->update([
            'status'           => VoucherStatus::Rejected->value,
            'rejection_reason' => $reason,
            'approved_by'      => $rejector->id,
            'approved_at'      => now(),
        ]);

        Log::info('Voucher rejected', [
            'voucher_id' => $voucher->id,
            'tenant_id'  => $voucher->tenant_id,
            'rejected_by'=> $rejector->id,
            'reason'     => $reason,
        ]);
    }
}
