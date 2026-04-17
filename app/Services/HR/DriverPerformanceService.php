<?php

namespace App\Services\HR;

use App\Enums\ShipmentStatus;
use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Şoförler için performans skoru hesaplar.
 *
 * Puan bileşenleri (0–100):
 *  • Teslim edilen sevkiyat (son 90 gün): +4 puan/sevkiyat, max 40
 *  • Süresi dolmuş belge: –20 puan/belge
 *  • 60 taban puan
 *
 * Sonuç 0–100 aralığında sıkıştırılır.
 */
class DriverPerformanceService
{
    /**
     * Belirli bir çalışanın performans skorunu hesaplar.
     *
     * @return array{score: int, deliveries_90d: int, expired_docs: int, grade: string}
     */
    public function scoreForEmployee(Employee $employee): array
    {
        $deliveries = $this->deliveryCount($employee);
        $expiredDocs = $this->expiredDocumentCount($employee);

        $score = $this->computeScore($deliveries, $expiredDocs);
        $grade = $this->grade($score);

        return [
            'score' => $score,
            'deliveries_90d' => $deliveries,
            'expired_docs' => $expiredDocs,
            'grade' => $grade,
        ];
    }

    /**
     * Kiracıya göre sıralanmış ilk $limit şoför listesini döner.
     *
     * @return Collection<int, array{employee: Employee, score: int, deliveries_90d: int, expired_docs: int, grade: string}>
     */
    public function leaderboard(int $tenantId, int $limit = 5): Collection
    {
        $drivers = Employee::query()
            ->where('tenant_id', $tenantId)
            ->where('is_driver', true)
            ->get();

        return $drivers
            ->map(function (Employee $emp): array {
                return array_merge(['employee' => $emp], $this->scoreForEmployee($emp));
            })
            ->sortByDesc('score')
            ->take($limit)
            ->values();
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    private function deliveryCount(Employee $employee): int
    {
        return (int) DB::table('shipments')
            ->where('driver_employee_id', $employee->id)
            ->where('tenant_id', $employee->tenant_id)
            ->where('status', ShipmentStatus::Delivered->value)
            ->where('updated_at', '>=', now()->subDays(90))
            ->count();
    }

    private function expiredDocumentCount(Employee $employee): int
    {
        return (int) DB::table('documents')
            ->where('documentable_type', Employee::class)
            ->where('documentable_id', $employee->id)
            ->where('tenant_id', $employee->tenant_id)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now()->toDateString())
            ->count();
    }

    private function computeScore(int $deliveries, int $expiredDocs): int
    {
        $score = 60
            + min(40, $deliveries * 4)
            - ($expiredDocs * 20);

        return max(0, min(100, $score));
    }

    private function grade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A',
            $score >= 75 => 'B',
            $score >= 60 => 'C',
            $score >= 40 => 'D',
            default => 'F',
        };
    }
}
