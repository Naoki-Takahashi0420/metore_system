<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\CustomerSubscription;
use Livewire\Attributes\On;

class PaymentFailedAlertWidget extends Widget
{
    protected static string $view = 'filament.widgets.payment-failed-alert';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 15;

    protected static ?string $pollingInterval = '60s';

    public $failedSubscriptions = [];
    public $selectedStoreId = null;

    public function mount(): void
    {
        $user = auth()->user();

        if ($user->hasRole('super_admin')) {
            $firstStore = \App\Models\Store::first();
            $this->selectedStoreId = $firstStore?->id;
        } else {
            $this->selectedStoreId = $user->store_id;
        }

        $this->loadFailedSubscriptions();
    }

    #[On('store-changed')]
    public function updateStore($storeId, $date = null): void
    {
        $this->selectedStoreId = $storeId;
        $this->loadFailedSubscriptions();
    }

    public function loadFailedSubscriptions(): void
    {
        $query = CustomerSubscription::query()
            ->with(['customer', 'store'])
            ->where('payment_failed', true)
            ->where('status', 'active')
            ->orderBy('payment_failed_at', 'desc');

        if ($this->selectedStoreId) {
            $query->where('store_id', $this->selectedStoreId);
        }

        $results = $query->get();

        $this->failedSubscriptions = $results->map(function ($subscription) {
            return [
                'id' => $subscription->id,
                'customer_name' => $subscription->customer->last_name . ' ' . $subscription->customer->first_name,
                'customer_phone' => $subscription->customer->phone,
                'plan_name' => $subscription->plan_name,
                'failed_at' => $subscription->payment_failed_at,
                'failed_reason' => $subscription->payment_failed_reason_display ?? '理由未設定',
                'failed_notes' => $subscription->payment_failed_notes,
                'monthly_price' => $subscription->monthly_price,
            ];
        })->toArray();
    }

    public function getSubscriptionEditUrl(int $id): string
    {
        return route('filament.admin.resources.subscriptions.edit', ['record' => $id]);
    }

    public static function canView(): bool
    {
        return true;
    }
}