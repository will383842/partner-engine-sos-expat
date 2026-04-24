<?php

namespace App\Filament\Partner\Widgets;

use App\Models\SubscriberActivity;
use Filament\Widgets\ChartWidget;

/**
 * Bar chart of top 10 countries where the partner's clients placed calls
 * from, this month. Helps partners see their geographic footprint.
 */
class TopCountriesWidget extends ChartWidget
{
    protected static ?string $heading = 'Top 10 pays d\'intervention (ce mois)';
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 'full';

    protected function getData(): array
    {
        $user = auth()->user();
        $partnerId = $user?->partner_firebase_id;
        if (!$partnerId) {
            return ['datasets' => [['label' => 'Appels', 'data' => []]], 'labels' => []];
        }

        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        $countryCounts = SubscriberActivity::where('partner_firebase_id', $partnerId)
            ->where('type', 'call_completed')
            ->whereBetween('created_at', [$monthStart, $monthEnd])
            ->whereNotNull('metadata')
            ->get()
            ->map(fn($r) => $r->metadata['country'] ?? null)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10);

        return [
            'datasets' => [[
                'label' => 'Appels',
                'data' => $countryCounts->values()->toArray(),
                'backgroundColor' => 'rgba(220, 38, 38, 0.7)',
                'borderColor' => 'rgb(220, 38, 38)',
                'borderWidth' => 1,
            ]],
            'labels' => $countryCounts->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'scales' => [
                'x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => ['legend' => ['display' => false]],
        ];
    }
}
