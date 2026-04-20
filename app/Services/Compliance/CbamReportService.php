<?php

namespace App\Services\Compliance;

use App\Models\CbamReport;
use App\Models\Shipment;
use Illuminate\Support\Collection;

/**
 * CBAM (Carbon Border Adjustment Mechanism) Report Service.
 *
 * Calculates CO2 equivalent emissions for road freight shipments.
 *
 * Emission factors (EU default values for road freight trucks):
 *  - Diesel truck: 2.64 kg CO2 per litre
 *  - Average fuel consumption: 0.33 l/km (30 l/100km) for laden truck
 *
 * For CBAM compliance the shipper must report:
 *  - Transport distance (km)
 *  - Fuel consumption (litres)
 *  - CO2 kg equivalent
 *  - Tonnage carried
 */
final class CbamReportService
{
    /** kg CO2 per litre diesel (EU default) */
    private const CO2_PER_LITRE = 2.64;

    /** litres per km for laden heavy truck (EU average) */
    private const FUEL_L_PER_KM = 0.33;

    /**
     * Calculate CO2 for a given distance and optional fuel consumption.
     * If fuel consumption is not provided, the EU default factor is used.
     */
    public function calculateCo2(float $distanceKm, ?float $fuelLitres = null): float
    {
        $fuel = $fuelLitres ?? ($distanceKm * self::FUEL_L_PER_KM);

        return round($fuel * self::CO2_PER_LITRE, 3);
    }

    /**
     * Generate a CBAM report from a shipment and persist it.
     */
    public function generateFromShipment(Shipment $shipment): CbamReport
    {
        $distanceKm = (float) ($shipment->order?->distance_km ?? 0);
        $tonnage = (float) ($shipment->order?->net_weight_kg
            ? $shipment->order->net_weight_kg / 1000
            : ($shipment->order?->tonnage ?? 0));
        $fuelL = $distanceKm * self::FUEL_L_PER_KM;
        $co2Kg = $this->calculateCo2($distanceKm, $fuelL);

        return CbamReport::create([
            'tenant_id' => $shipment->tenant_id,
            'shipment_id' => $shipment->id,
            'co2_kg' => $co2Kg,
            'distance_km' => $distanceKm > 0 ? $distanceKm : null,
            'fuel_consumption_l' => $distanceKm > 0 ? $fuelL : null,
            'tonnage' => $tonnage > 0 ? $tonnage : null,
            'vehicle_type' => 'truck',
            'report_date' => $shipment->delivered_at?->toDateString() ?? now()->toDateString(),
            'status' => 'draft',
        ]);
    }

    /**
     * Export reports as CSV string.
     *
     * @param  Collection<int, CbamReport>  $reports
     */
    public function toCsv(Collection $reports): string
    {
        $header = ['id', 'report_date', 'shipment_id', 'distance_km', 'fuel_l', 'co2_kg', 'tonnage', 'vehicle_type', 'status'];
        $rows = $reports->map(fn (CbamReport $r) => [
            $r->id,
            $r->report_date?->format('Y-m-d'),
            $r->shipment_id ?? '',
            $r->distance_km ?? '',
            $r->fuel_consumption_l ?? '',
            $r->co2_kg,
            $r->tonnage ?? '',
            $r->vehicle_type,
            $r->status,
        ]);

        $csv = implode(',', $header)."\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($v) => '"'.str_replace('"', '""', (string) $v).'"', $row))."\n";
        }

        return $csv;
    }
}
