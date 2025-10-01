@php
    $version = null;
    $versionFile = base_path('.version');

    // デプロイ時に作成される.versionファイルから読み込み
    if (file_exists($versionFile)) {
        $version = trim(file_get_contents($versionFile));
    } else {
        // ローカル環境ではgitコマンドで取得
        try {
            $version = trim(shell_exec('git rev-parse --short HEAD 2>/dev/null') ?: '');
        } catch (\Exception $e) {
            $version = 'dev';
        }
    }

    if (empty($version)) {
        $version = 'unknown';
    }
@endphp

<div style="position: fixed; bottom: 8px; right: 12px; font-size: 10px; color: #9ca3af; z-index: 1000; opacity: 0.6;">
    v{{ $version }}
</div>
