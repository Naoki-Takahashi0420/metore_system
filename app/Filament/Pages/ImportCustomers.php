<?php

namespace App\Filament\Pages;

use App\Services\CustomerImportService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\WithFileUploads;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportCustomers extends Page
{
    use WithFileUploads;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';
    protected static string $view = 'filament.pages.import-customers';
    protected static ?string $title = '顧客データインポート';
    protected static ?string $navigationLabel = '顧客インポート';
    protected static ?string $navigationGroup = '顧客管理';
    protected static ?int $navigationSort = 100;

    public $csvFile = null;
    public ?int $store_id = null;
    public ?array $importResults = null;
    public bool $isProcessing = false;

    public function mount(): void
    {
        // デフォルト店舗を設定
        $user = auth()->user();
        if ($user->store_id) {
            $this->store_id = $user->store_id;
        }
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('インポート設定')
                    ->description('CSVファイルをアップロードして顧客データをインポートします')
                    ->schema([
                        Forms\Components\Select::make('store_id')
                            ->label('インポート先店舗')
                            ->options(\App\Models\Store::pluck('name', 'id'))
                            ->required()
                            ->helperText('この店舗の顧客としてインポートされます'),
                        
                        Forms\Components\FileUpload::make('csvFile')
                            ->label('CSVファイル')
                            ->acceptedFileTypes(['text/csv', 'application/csv', 'text/plain'])
                            ->maxSize(10240) // 10MB
                            ->required()
                            ->helperText('Shift-JIS形式のCSVファイルをアップロードしてください'),
                    ]),
                
                Forms\Components\Section::make('インポート仕様')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Forms\Components\Placeholder::make('specification')
                            ->content(function () {
                                return view('filament.components.import-specification');
                            }),
                    ]),
            ]);
    }

    public function import(): void
    {
        $this->validate([
            'csvFile' => 'required',
            'store_id' => 'required|exists:stores,id',
        ]);

        $this->isProcessing = true;
        $this->importResults = null;

        try {
            // アップロードされたファイルを保存
            $path = $this->csvFile->store('imports', 'local');
            $fullPath = Storage::disk('local')->path($path);

            // インポートサービスを実行
            $importService = new CustomerImportService();
            $results = $importService->import($fullPath, $this->store_id);

            // 結果を保存
            $this->importResults = $results;

            // エラーログがある場合はファイルとして保存
            if (!empty($results['errors'])) {
                $errorCsv = $importService->exportErrorLog($results['errors']);
                $errorFileName = 'import_errors_' . now()->format('YmdHis') . '.csv';
                Storage::disk('local')->put('exports/' . $errorFileName, $errorCsv);
                $this->importResults['error_file'] = $errorFileName;
            }

            // インポート履歴を記録
            \DB::table('customer_imports')->insert([
                'store_id' => $this->store_id,
                'user_id' => auth()->id(),
                'file_name' => $this->csvFile->getClientOriginalName(),
                'total_rows' => $results['success_count'] + $results['skip_count'] + $results['error_count'],
                'success_count' => $results['success_count'],
                'skip_count' => $results['skip_count'],
                'error_count' => $results['error_count'],
                'error_log' => json_encode($results['errors']),
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 成功通知
            if ($results['success_count'] > 0) {
                Notification::make()
                    ->title('インポート完了')
                    ->body("{$results['success_count']}件の顧客データをインポートしました")
                    ->success()
                    ->send();
            }

            // 警告通知
            if ($results['skip_count'] > 0 || $results['error_count'] > 0) {
                $message = [];
                if ($results['skip_count'] > 0) {
                    $message[] = "スキップ: {$results['skip_count']}件";
                }
                if ($results['error_count'] > 0) {
                    $message[] = "エラー: {$results['error_count']}件";
                }
                
                Notification::make()
                    ->title('インポート時の注意')
                    ->body(implode('、', $message))
                    ->warning()
                    ->send();
            }

            // 一時ファイルを削除
            Storage::disk('local')->delete($path);

        } catch (\Exception $e) {
            Notification::make()
                ->title('インポートエラー')
                ->body('インポート処理中にエラーが発生しました: ' . $e->getMessage())
                ->danger()
                ->send();

            \Log::error('Customer import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        } finally {
            $this->isProcessing = false;
        }
    }

    public function downloadErrorLog(): void
    {
        if ($this->importResults && isset($this->importResults['error_file'])) {
            $path = 'exports/' . $this->importResults['error_file'];
            
            if (Storage::disk('local')->exists($path)) {
                $this->dispatch('download-file', [
                    'filename' => $this->importResults['error_file'],
                    'content' => Storage::disk('local')->get($path),
                    'mimeType' => 'text/csv'
                ]);
            }
        }
    }

    public function downloadTemplate(): void
    {
        $template = <<<CSV
顧客番号,顧客名,ふりがな,メールアドレス,性別,電話番号1,電話番号2,電話番号3,誕生日,郵便番号,住所,建物名,記念日,顧客特性,来店区分,血液型,来店動機,来店詳細,顧客登録日時,更新日時
001,山田太郎,やまだたろう,yamada@example.com,男性,09012345678,,,1980/1/1,1234567,東京都千代田区丸の内1-1-1,○○ビル5F,,,新規,A型,紹介,友人の紹介,2024/1/1 10:00,2024/1/1 10:00
CSV;

        $this->dispatch('download-file', [
            'filename' => 'customer_import_template.csv',
            'content' => mb_convert_encoding($template, 'SJIS-win', 'UTF-8'),
            'mimeType' => 'text/csv'
        ]);
    }
}