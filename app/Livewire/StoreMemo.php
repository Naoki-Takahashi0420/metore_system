<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Store;

class StoreMemo extends Component
{
    public bool $isOpen = false;
    public string $memo = '';
    public ?int $storeId = null;
    public ?string $storeName = null;
    public bool $isSaving = false;
    public ?string $successMessage = null;
    public bool $canSelectStore = false;
    public array $availableStores = [];

    public function mount(): void
    {
        $this->loadStoreMemo();
    }

    public function loadStoreMemo(): void
    {
        $user = auth()->user();

        if (!$user) {
            return;
        }

        // ユーザーの店舗を取得
        if ($user->store_id) {
            $store = Store::find($user->store_id);
            $this->canSelectStore = false;
        } elseif ($user->hasRole('super_admin')) {
            // super_adminの場合は全店舗から選択可能
            $this->availableStores = Store::where('is_active', true)
                ->pluck('name', 'id')
                ->toArray();
            $store = Store::where('is_active', true)->first();
            $this->canSelectStore = true;
        } elseif ($user->hasRole('owner')) {
            // オーナーの場合は管理店舗から選択可能
            $this->availableStores = $user->manageableStores()
                ->where('stores.is_active', true)
                ->pluck('stores.name', 'stores.id')
                ->toArray();
            $store = $user->manageableStores()->first();
            $this->canSelectStore = count($this->availableStores) > 1;
        } else {
            $store = null;
        }

        if ($store) {
            $this->storeId = $store->id;
            $this->storeName = $store->name;
            $this->memo = $store->memo ?? '';
        }
    }

    public function selectStore(int $storeId): void
    {
        $store = Store::find($storeId);
        if ($store) {
            $this->storeId = $store->id;
            $this->storeName = $store->name;
            $this->memo = $store->memo ?? '';
            $this->successMessage = null;
        }
    }

    public function open(): void
    {
        $this->loadStoreMemo();
        $this->isOpen = true;
        $this->successMessage = null;
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->successMessage = null;
    }

    public function save(): void
    {
        if (!$this->storeId) {
            return;
        }

        $this->isSaving = true;

        try {
            Store::where('id', $this->storeId)->update([
                'memo' => $this->memo,
            ]);

            $this->successMessage = '保存しました';

            // 3秒後にメッセージを消す
            $this->dispatch('memo-saved');
        } catch (\Exception $e) {
            $this->successMessage = 'エラーが発生しました';
        } finally {
            $this->isSaving = false;
        }
    }

    public function render()
    {
        return view('livewire.store-memo');
    }
}
