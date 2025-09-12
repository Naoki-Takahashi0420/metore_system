// Medical Record FileUpload Debug Script
document.addEventListener('DOMContentLoaded', function() {
    console.log('Medical Record Debug Script Loaded');
    
    // FileUploadコンポーネントの監視
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.target.classList && mutation.target.classList.contains('filepond')) {
                console.log('FilePond detected:', mutation.target);
                
                // FilePondインスタンスの取得
                const pond = FilePond.find(mutation.target);
                if (pond) {
                    console.log('FilePond instance found:', pond);
                    
                    // イベントリスナーの追加
                    pond.on('init', () => {
                        console.log('FilePond initialized');
                    });
                    
                    pond.on('addfile', (error, file) => {
                        console.log('File added:', file);
                        if (error) {
                            console.error('Error adding file:', error);
                        }
                    });
                    
                    pond.on('processfile', (error, file) => {
                        console.log('File processed:', file);
                        if (error) {
                            console.error('Error processing file:', error);
                        }
                    });
                    
                    pond.on('error', (error) => {
                        console.error('FilePond error:', error);
                    });
                    
                    pond.on('load', (source, load, error) => {
                        console.log('FilePond load event:', { source, load, error });
                    });
                }
            }
        });
    });
    
    // 監視開始
    observer.observe(document.body, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['class']
    });
    
    // Livewire フックの追加
    if (window.Livewire) {
        console.log('Livewire detected');
        
        Livewire.hook('message.sent', (message, component) => {
            console.log('Livewire message sent:', message);
        });
        
        Livewire.hook('message.received', (message, component) => {
            console.log('Livewire message received:', message);
        });
        
        Livewire.hook('message.processed', (message, component) => {
            console.log('Livewire message processed:', message);
        });
        
        Livewire.hook('element.updated', (el, component) => {
            if (el.querySelector('.filepond')) {
                console.log('FilePond element updated');
            }
        });
    }
    
    // Ajax リクエストの監視
    const originalFetch = window.fetch;
    window.fetch = function(...args) {
        console.log('Fetch request:', args[0]);
        return originalFetch.apply(this, args)
            .then(response => {
                console.log('Fetch response:', response.status, response.url);
                if (!response.ok) {
                    console.error('Fetch error:', response.status, response.statusText);
                }
                return response;
            })
            .catch(error => {
                console.error('Fetch exception:', error);
                throw error;
            });
    };
});