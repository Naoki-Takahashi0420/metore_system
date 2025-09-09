{{-- カレンダーの日付クリックを検出してタイムラインを更新するスクリプト --}}
<script>
(function() {
    let isSetup = false;
    
    function setupCalendarDateClick() {
        if (isSetup) return;
        
        console.log('Setting up calendar date click detection...');
        
        // 初回セットアップを実行
        attachDateClickHandlers();
        
        // MutationObserverで動的に追加される要素を監視
        const observer = new MutationObserver(function(mutations) {
            // カレンダー構造が変更されたら再アタッチ
            const hasCalendarChange = mutations.some(mutation => {
                return mutation.target.classList && 
                       (mutation.target.classList.contains('fc') || 
                        mutation.target.classList.contains('fc-view') ||
                        mutation.target.classList.contains('fc-daygrid'));
            });
            
            if (hasCalendarChange) {
                setTimeout(attachDateClickHandlers, 100);
            }
        });
        
        // 監視を開始
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: true,
            attributeFilter: ['class']
        });
        
        isSetup = true;
    }
    
    function attachDateClickHandlers() {
        console.log('Attaching date click handlers...');
        
        // 正しいセレクタを使用（確認したHTML構造に基づく）
        const dayNumbers = document.querySelectorAll('a.fc-daygrid-day-number');
        console.log('Found day numbers:', dayNumbers.length);
        
        if (dayNumbers.length === 0) {
            console.log('No day numbers found. Retrying in 1 second...');
            setTimeout(attachDateClickHandlers, 1000);
            return;
        }
        
        dayNumbers.forEach(dayNumber => {
            // 既にセットアップ済みの場合はスキップ
            if (dayNumber.dataset.clickSetup) return;
            
            // スタイルを設定
            dayNumber.style.cursor = 'pointer';
            dayNumber.style.userSelect = 'none';
            dayNumber.style.display = 'inline-block';
            dayNumber.style.padding = '2px 6px';
            dayNumber.style.transition = 'all 0.2s';
            
            // ホバー効果を追加
            dayNumber.addEventListener('mouseenter', function() {
                this.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
                this.style.borderRadius = '4px';
                this.style.transform = 'scale(1.1)';
            });
            
            dayNumber.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
                this.style.transform = '';
            });
            
            // クリックイベントを追加
            dayNumber.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Day number clicked:', this.textContent);
                
                // 親要素から日付を取得（tdタグを探す）
                let dayCell = this.closest('td.fc-daygrid-day');
                if (!dayCell) {
                    dayCell = this.closest('td[data-date]');
                }
                
                if (dayCell) {
                    const dateStr = dayCell.getAttribute('data-date');
                    console.log('Found date:', dateStr);
                    
                    if (dateStr) {
                        // 視覚的フィードバック
                        this.style.backgroundColor = 'rgba(59, 130, 246, 0.4)';
                        setTimeout(() => {
                            this.style.backgroundColor = '';
                        }, 200);
                        
                        // Livewireイベントを発火
                        if (window.Livewire) {
                            console.log('Dispatching calendar-date-clicked event with date:', dateStr);
                            window.Livewire.dispatch('calendar-date-clicked', { date: dateStr });
                        } else {
                            console.error('Livewire not found!');
                        }
                    }
                } else {
                    console.log('Could not find parent day cell');
                }
            });
            
            // セットアップ済みマーク
            dayNumber.dataset.clickSetup = 'true';
        });
        
        // 週表示・日表示の場合の日付ヘッダーも対応
        const colHeaders = document.querySelectorAll('.fc-col-header-cell-cushion');
        console.log('Found column headers:', colHeaders.length);
        
        colHeaders.forEach(header => {
            if (header.dataset.clickSetup) return;
            
            header.style.cursor = 'pointer';
            
            header.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                const headerCell = this.closest('.fc-col-header-cell');
                if (headerCell) {
                    const dateStr = headerCell.getAttribute('data-date');
                    if (dateStr) {
                        console.log('Week/Day view date clicked:', dateStr);
                        
                        if (window.Livewire) {
                            window.Livewire.dispatch('calendar-date-clicked', { date: dateStr });
                        }
                    }
                }
            });
            
            header.dataset.clickSetup = 'true';
        });
    }
    
    // DOMContentLoadedで初期化
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(setupCalendarDateClick, 1500);
        });
    } else {
        // 既に読み込まれている場合
        setTimeout(setupCalendarDateClick, 1500);
    }
    
    // Livewireナビゲーション時にも再初期化
    document.addEventListener('livewire:navigated', function() {
        isSetup = false;
        setTimeout(setupCalendarDateClick, 1500);
    });
})();
</script>