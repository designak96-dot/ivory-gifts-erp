<?php

namespace App\Services;

use App\Models\{DeliveryNote, Setting};
use Carbon\Carbon;
use RuntimeException;

class DeliverySchedulingService
{
    public function dailyLimit(): int
    {
        return max(1, (int) Setting::value('delivery_limit_per_day', 6));
    }

    public function scheduledCount(Carbon|string $date, ?int $exceptDeliveryId = null): int
    {
        return DeliveryNote::query()
            ->whereDate('delivery_date', Carbon::parse($date))
            ->whereNotIn('status', ['delivered', 'returned'])
            ->when($exceptDeliveryId, fn ($query) => $query->whereKeyNot($exceptDeliveryId))
            ->count();
    }

    public function nextAvailableDate(Carbon|string|null $from = null, ?int $exceptDeliveryId = null): Carbon
    {
        $date = Carbon::parse($from ?: today())->startOfDay();
        $limit = $this->dailyLimit();

        for ($day = 0; $day < 366; $day++, $date->addDay()) {
            if ($this->scheduledCount($date, $exceptDeliveryId) < $limit) {
                return $date->copy();
            }
        }

        throw new RuntimeException('No available delivery date was found in the next 12 months.');
    }

    public function ensureAvailable(Carbon|string $date, ?int $exceptDeliveryId = null): void
    {
        if ($this->scheduledCount($date, $exceptDeliveryId) >= $this->dailyLimit()) {
            $next = $this->nextAvailableDate(Carbon::parse($date)->addDay(), $exceptDeliveryId);
            throw new RuntimeException('That date is fully booked. Next available date: '.$next->format('d M Y').'.');
        }
    }

    public function charge(string|null $emirate, string $packageSize): float
    {
        $emirate = mb_strtolower(trim((string) $emirate));

        if ($packageSize === 'pickup') {
            return 0;
        }
        if ($packageSize === 'large') {
            if (str_contains($emirate, 'western') || str_contains($emirate, 'al dhafra')) {
                throw new RuntimeException('Large stands are not available for delivery to the Western Region.');
            }
            return str_contains($emirate, 'abu dhabi') ? 100 : 150;
        }

        return (str_contains($emirate, 'western') || str_contains($emirate, 'al dhafra')) ? 60 : 40;
    }
}
