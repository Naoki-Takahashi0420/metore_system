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
                // 視力推移グラフ
                Components\Section::make('視力推移グラフ')
                    ->description('複数回の施術による視力の変化を表示しています')
                    ->schema([
                        Components\View::make('filament.resources.medical-record.vision-chart')
                            ->viewData(fn ($record) => ['record' => $record])
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->visible(fn ($record) => !empty($record->vision_records)),

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
                                            ->label('対応者'),
                                        Components\TextEntry::make('treatment_date')
                                            ->label('施術日')
                                            ->date('Y/m/d'),
                                    ]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}