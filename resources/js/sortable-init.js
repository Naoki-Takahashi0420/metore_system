import Sortable from 'sortablejs';

// Filament用のSortable初期化
document.addEventListener('DOMContentLoaded', function() {
    initializeSortable();
});

// Livewireコンポーネント更新後の再初期化
document.addEventListener('livewire:load', function() {
    Livewire.hook('message.processed', (message, component) => {
        initializeSortable();
    });
});

function initializeSortable() {
    // メニューカテゴリーのソート
    const categoryLists = document.querySelectorAll('.sortable-categories');
    categoryLists.forEach(list => {
        if (list.dataset.sortableInitialized) return;
        
        Sortable.create(list, {
            animation: 150,
            handle: '.sortable-handle',
            ghostClass: 'bg-gray-100',
            dragClass: 'opacity-50',
            onEnd: function(evt) {
                const items = Array.from(list.children);
                const orders = items.map((item, index) => ({
                    id: item.dataset.id,
                    order: index
                }));
                
                // Livewireコンポーネントに順序を送信
                Livewire.emit('updateCategoryOrder', orders);
            }
        });
        
        list.dataset.sortableInitialized = 'true';
    });
    
    // メニューアイテムのソート
    const menuLists = document.querySelectorAll('.sortable-menus');
    menuLists.forEach(list => {
        if (list.dataset.sortableInitialized) return;
        
        Sortable.create(list, {
            animation: 150,
            handle: '.sortable-handle',
            ghostClass: 'bg-blue-50',
            dragClass: 'opacity-50',
            group: {
                name: 'menus',
                pull: true,
                put: true
            },
            onEnd: function(evt) {
                const categoryId = list.dataset.categoryId;
                const items = Array.from(list.children);
                const orders = items.map((item, index) => ({
                    id: item.dataset.id,
                    category_id: categoryId,
                    order: index
                }));
                
                // Livewireコンポーネントに順序を送信
                Livewire.emit('updateMenuOrder', orders);
            }
        });
        
        list.dataset.sortableInitialized = 'true';
    });
    
    // メニューオプションのソート
    const optionTables = document.querySelectorAll('table.filament-tables-table tbody');
    optionTables.forEach(tbody => {
        // OptionsRelationManagerのテーブルか確認
        if (!tbody.closest('[wire\\:id]')?.querySelector('[wire\\:key*="options-relation-manager"]')) return;
        if (tbody.dataset.sortableInitialized) return;
        
        Sortable.create(tbody, {
            animation: 150,
            handle: 'tr',
            draggable: 'tr',
            ghostClass: 'bg-yellow-50',
            dragClass: 'opacity-50',
            onEnd: function(evt) {
                const rows = Array.from(tbody.querySelectorAll('tr'));
                const orders = rows.map((row, index) => {
                    const recordId = row.querySelector('[wire\\:key]')?.getAttribute('wire:key')?.match(/record-(\d+)/)?.[1];
                    return {
                        id: recordId,
                        order: index
                    };
                }).filter(item => item.id);
                
                // Livewireコンポーネントに順序を送信
                if (orders.length > 0) {
                    Livewire.emit('updateOptionOrder', orders);
                }
            }
        });
        
        tbody.dataset.sortableInitialized = 'true';
    });
}

// カテゴリー並び替え用のカスタムビュー対応
window.initCategorySortable = function(element) {
    if (element.dataset.sortableInitialized) return;
    
    Sortable.create(element, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            const items = Array.from(element.children);
            const data = items.map((item, index) => ({
                id: parseInt(item.dataset.id),
                sort_order: index
            }));
            
            // AJAXでサーバーに送信
            fetch('/admin/menu-categories/update-order', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ categories: data })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 成功通知
                    if (window.FilamentNotifications) {
                        window.FilamentNotifications.notify('success', {
                            title: '並び順を更新しました',
                            body: 'カテゴリーの表示順序が変更されました。'
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // エラー通知
                if (window.FilamentNotifications) {
                    window.FilamentNotifications.notify('danger', {
                        title: 'エラー',
                        body: '並び順の更新に失敗しました。'
                    });
                }
            });
        }
    });
    
    element.dataset.sortableInitialized = 'true';
};