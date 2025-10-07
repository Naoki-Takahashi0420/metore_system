<?php

namespace App\Filament\Resources\MedicalRecordResource\Pages;

use App\Filament\Resources\MedicalRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components;

class ViewMedicalRecord extends ViewRecord
{
    protected static string $resource = MedicalRecordResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // 顧客の全カルテ履歴タイムライン
                Components\Section::make('カルテ履歴タイムライン')
                    ->description('この顧客の全てのカルテを時系列で表示')
                    ->schema([
                        Components\View::make('filament.resources.medical-record.timeline')
                            ->viewData(function ($record) {
                                $allRecords = \App\Models\MedicalRecord::where('customer_id', $record->customer_id)
                                    ->with(['reservation.store', 'reservation.menu', 'createdBy'])
                                    ->orderBy('treatment_date', 'desc')
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                return [
                                    'currentRecord' => $record,
                                    'allRecords' => $allRecords,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(false),

                // 顧客画像
                Components\Section::make('顧客画像')
                    ->description('顧客管理から登録された画像')
                    ->schema([
                        Components\View::make('filament.resources.medical-record.customer-images')
                            ->viewData(function ($record) {
                                $images = \App\Models\CustomerImage::where('customer_id', $record->customer_id)
                                    ->where('is_visible_to_customer', true)
                                    ->orderBy('display_order', 'asc')
                                    ->orderBy('created_at', 'desc')
                                    ->get();

                                return [
                                    'images' => $images,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(function ($record) {
                        // 顧客に画像があるかチェック
                        return \App\Models\CustomerImage::where('customer_id', $record->customer_id)
                            ->where('is_visible_to_customer', true)
                            ->exists();
                    }),

                // 顧客の予約一覧
                Components\Section::make('予約一覧')
                    ->description('この顧客の全ての予約（過去・未来）')
                    ->schema([
                        Components\View::make('filament.resources.medical-record.reservations')
                            ->viewData(function ($record) {
                                $reservations = \App\Models\Reservation::where('customer_id', $record->customer_id)
                                    ->with(['store', 'menu', 'staff'])
                                    ->orderBy('reservation_date', 'desc')
                                    ->orderBy('start_time', 'desc')
                                    ->get();

                                return [
                                    'reservations' => $reservations,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(true),

                // 視力推移グラフ
                Components\Section::make('視力推移グラフ')
                    ->description('この顧客の全カルテから視力の変化を表示しています')
                    ->schema([
                        Components\View::make('filament.resources.medical-record.vision-chart')
                            ->viewData(function ($record) {
                                // 顧客の全カルテを取得（時系列順）
                                $allRecords = \App\Models\MedicalRecord::where('customer_id', $record->customer_id)
                                    ->orderBy('treatment_date', 'asc')
                                    ->orderBy('created_at', 'asc')
                                    ->get();

                                return [
                                    'record' => $record,
                                    'allRecords' => $allRecords,
                                ];
                            })
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(function ($record) {
                        // 顧客の全カルテに視力データがあるかチェック
                        return \App\Models\MedicalRecord::where('customer_id', $record->customer_id)
                            ->whereNotNull('vision_records')
                            ->exists();
                    }),

                // 既存のフォームフィールドをinfolistとして表示
                Components\Tabs::make('カルテ情報')
                    ->tabs([
                        Components\Tabs\Tab::make('基本情報')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('customer.full_name')
                                            ->label('顧客')
                                            ->getStateUsing(fn ($record) =>
                                                ($record->customer->last_name ?? '') . ' ' . ($record->customer->first_name ?? '')
                                            ),
                                        Components\TextEntry::make('reservation.formatted')
                                            ->label('予約')
                                            ->getStateUsing(function ($record) {
                                                if (!$record->reservation) return '予約なし（引き継ぎメモなど）';
                                                $r = $record->reservation;
                                                $date = $r->reservation_date ? $r->reservation_date->format('Y/m/d') : '';
                                                $time = $r->start_time ? ' ' . date('H:i', strtotime($r->start_time)) : '';
                                                $menu = $r->menu ? ' - ' . $r->menu->name : '';
                                                $store = $r->store ? ' (' . $r->store->name . ')' : '';
                                                return $date . $time . $menu . $store;
                                            }),
                                        Components\TextEntry::make('handled_by')
                                            ->label('対応者')
                                            ->placeholder('記載なし'),
                                        Components\TextEntry::make('treatment_date')
                                            ->label('施術日')
                                            ->date('Y/m/d'),
                                        Components\TextEntry::make('age')
                                            ->label('年齢')
                                            ->suffix('歳')
                                            ->placeholder('記載なし'),
                                    ]),
                            ]),
                        Components\Tabs\Tab::make('顧客管理情報')
                            ->schema([
                                Components\Grid::make(2)
                                    ->schema([
                                        Components\TextEntry::make('payment_method')
                                            ->label('支払い方法')
                                            ->placeholder('記載なし'),
                                        Components\TextEntry::make('reservation_source')
                                            ->label('来店経路')
                                            ->placeholder('記載なし'),
                                        Components\TextEntry::make('visit_purpose')
                                            ->label('来店目的')
                                            ->placeholder('記載なし')
                                            ->columnSpanFull(),
                                        Components\TextEntry::make('workplace_address')
                                            ->label('職場・住所')
                                            ->placeholder('記載なし')
                                            ->columnSpanFull(),
                                        Components\IconEntry::make('genetic_possibility')
                                            ->label('遺伝の可能性')
                                            ->boolean(),
                                        Components\IconEntry::make('has_astigmatism')
                                            ->label('乱視')
                                            ->boolean(),
                                        Components\TextEntry::make('eye_diseases')
                                            ->label('目の病気')
                                            ->placeholder('記載なし')
                                            ->columnSpanFull(),
                                        Components\TextEntry::make('device_usage')
                                            ->label('スマホ・PC使用頻度')
                                            ->placeholder('記載なし')
                                            ->columnSpanFull(),
                                    ]),
                            ]),
                        Components\Tabs\Tab::make('視力記録')
                            ->schema([
                                Components\RepeatableEntry::make('vision_records')
                                    ->label('視力測定記録')
                                    ->schema([
                                        Components\Grid::make(4)
                                            ->schema([
                                                Components\TextEntry::make('session')
                                                    ->label('回数')
                                                    ->suffix('回目'),
                                                Components\TextEntry::make('date')
                                                    ->label('測定日')
                                                    ->date('Y/m/d'),
                                                Components\TextEntry::make('intensity')
                                                    ->label('強度'),
                                                Components\TextEntry::make('duration')
                                                    ->label('時間')
                                                    ->suffix('分'),
                                            ]),
                                        Components\Section::make('施術前視力 - 裸眼')
                                            ->schema([
                                                Components\Grid::make(2)
                                                    ->schema([
                                                        Components\TextEntry::make('before_naked_left')
                                                            ->label('左眼')
                                                            ->placeholder('未測定'),
                                                        Components\TextEntry::make('before_naked_right')
                                                            ->label('右眼')
                                                            ->placeholder('未測定'),
                                                    ]),
                                            ])
                                            ->collapsible(),
                                        Components\Section::make('施術前視力 - 矯正（メガネ・コンタクト）')
                                            ->schema([
                                                Components\Grid::make(2)
                                                    ->schema([
                                                        Components\TextEntry::make('before_corrected_left')
                                                            ->label('左眼')
                                                            ->placeholder('未測定'),
                                                        Components\TextEntry::make('before_corrected_right')
                                                            ->label('右眼')
                                                            ->placeholder('未測定'),
                                                    ]),
                                            ])
                                            ->collapsible(),
                                        Components\Section::make('施術後視力 - 裸眼')
                                            ->schema([
                                                Components\Grid::make(2)
                                                    ->schema([
                                                        Components\TextEntry::make('after_naked_left')
                                                            ->label('左眼')
                                                            ->placeholder('未測定'),
                                                        Components\TextEntry::make('after_naked_right')
                                                            ->label('右眼')
                                                            ->placeholder('未測定'),
                                                    ]),
                                            ])
                                            ->collapsible(),
                                        Components\Section::make('施術後視力 - 矯正（メガネ・コンタクト）')
                                            ->schema([
                                                Components\Grid::make(2)
                                                    ->schema([
                                                        Components\TextEntry::make('after_corrected_left')
                                                            ->label('左眼')
                                                            ->placeholder('未測定'),
                                                        Components\TextEntry::make('after_corrected_right')
                                                            ->label('右眼')
                                                            ->placeholder('未測定'),
                                                    ]),
                                            ])
                                            ->collapsible(),
                                        Components\TextEntry::make('public_memo')
                                            ->label('メモ（顧客に表示）')
                                            ->placeholder('記載なし')
                                            ->columnSpanFull(),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Components\Tabs\Tab::make('接客メモ・引き継ぎ')
                            ->schema([
                                Components\TextEntry::make('service_memo')
                                    ->label('接客メモ（内部用・顧客には非表示）')
                                    ->placeholder('記載なし')
                                    ->columnSpanFull(),
                                Components\TextEntry::make('next_visit_notes')
                                    ->label('次回引き継ぎ事項')
                                    ->placeholder('記載なし')
                                    ->columnSpanFull(),
                                Components\TextEntry::make('notes')
                                    ->label('その他メモ')
                                    ->placeholder('記載なし')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}