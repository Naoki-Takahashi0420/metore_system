<?php

namespace App\Filament\Resources\AnnouncementResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ReadsRelationManager extends RelationManager
{
    protected static string $relationship = 'reads';

    protected static ?string $title = '既読状況';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // 既読ログは閲覧のみで編集不可
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $announcement = $this->getOwnerRecord();

        // 対象ユーザーを取得
        $query = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.store_id',
                'announcement_reads.read_at',
                DB::raw('CASE WHEN announcement_reads.id IS NOT NULL THEN 1 ELSE 0 END as is_read')
            ])
            ->leftJoin('announcement_reads', function ($join) use ($announcement) {
                $join->on('users.id', '=', 'announcement_reads.user_id')
                     ->where('announcement_reads.announcement_id', '=', $announcement->id);
            });

        // 対象範囲による絞り込み
        if ($announcement->target_type === 'specific_stores') {
            $storeIds = $announcement->stores()->pluck('stores.id');
            $query->whereIn('users.store_id', $storeIds);
        }

        return $query;
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\BadgeColumn::make('is_read')
                    ->label('状態')
                    ->formatStateUsing(fn ($state): string => $state ? '既読' : '未読')
                    ->colors([
                        'success' => fn ($state): bool => $state == 1,
                        'gray' => fn ($state): bool => $state == 0,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('ユーザー名')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('store.name')
                    ->label('所属店舗')
                    ->searchable()
                    ->sortable()
                    ->placeholder('未設定'),

                Tables\Columns\TextColumn::make('read_at')
                    ->label('既読日時')
                    ->dateTime('Y/m/d H:i:s')
                    ->sortable()
                    ->placeholder('-'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_read')
                    ->label('既読状態')
                    ->options([
                        '1' => '既読のみ',
                        '0' => '未読のみ',
                    ]),
            ])
            ->headerActions([
                // 既読ログの作成は不要
            ])
            ->actions([
                // 既読ログの編集・削除は不要
            ])
            ->bulkActions([
                // 一括操作も不要
            ])
            ->defaultSort('is_read', 'desc')
            ->paginated([10, 25, 50, 100])
            ->recordClasses(fn (Model $record) => $record->is_read ? '' : 'opacity-30');
    }
}
