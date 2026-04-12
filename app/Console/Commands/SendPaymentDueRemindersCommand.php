<?php

namespace App\Console\Commands;

use App\Mail\PaymentDueReminderMail;
use App\Models\Order;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendPaymentDueRemindersCommand extends Command
{
    protected $signature = 'logistics:send-payment-due-reminders
                            {--days=7 : Send reminders for orders due within this many days}';

    protected $description = 'Send payment due reminder emails for upcoming order payments';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $dueFrom = now()->startOfDay();
        $dueTo = now()->addDays($days)->endOfDay();

        // Group overdue/upcoming orders by tenant admin users
        $orders = Order::query()
            ->with('customer')
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$dueFrom, $dueTo])
            ->get();

        if ($orders->isEmpty()) {
            $this->info("No payment due orders in the next {$days} days.");

            return self::SUCCESS;
        }

        // Send to each tenant's admin users
        $byTenant = $orders->groupBy('tenant_id');
        $sent = 0;

        foreach ($byTenant as $tenantId => $tenantOrders) {
            $admins = User::query()
                ->where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'logistics.admin'))
                ->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->queue(
                    new PaymentDueReminderMail($tenantOrders->all(), $admin->name)
                );
                $sent++;
            }
        }

        $this->info("Sent {$sent} payment due reminder email(s) for {$orders->count()} orders.");

        return self::SUCCESS;
    }
}
