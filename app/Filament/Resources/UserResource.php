<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'ユーザー管理';

    protected static ?string $modelLabel = 'ユーザー';

    protected static ?string $pluralModelLabel = 'ユーザー';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本情報')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('名前')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('メールアドレス')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('パスワード')
                            ->password()
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->maxLength(255),
                        Forms\Components\Select::make('store_id')
                            ->label('所属店舗')
                            ->options(\App\Models\Store::whereNotNull('name')->where('name', '!=', '')->get()->pluck('name', 'id')->filter())
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('権限設定')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('ロール')
                            ->relationship('roles', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => mb_convert_encoding($record->display_name ?? $record->name ?? 'Unknown', 'UTF-8', 'auto') ?? 'Unknown')
                            ->preload()
                            ->required()
                            ->reactive()
                            ->multiple(false)
                            ->helperText('ユーザーの権限レベルを選択してください'),
                        Forms\Components\Select::make('manageable_stores')
                            ->label('管理可能店舗')
                            ->relationship('manageableStores', 'name')
                            ->multiple()
                            ->preload()
                            ->visible(function (callable $get) {
                                $selectedRoles = $get('roles');
                                if (!$selectedRoles) return false;
                                
                                // 複数選択の場合は配列、単一選択の場合は数値
                                $roleIds = is_array($selectedRoles) ? $selectedRoles : [$selectedRoles];
                                
                                // ロール名を確認
                                foreach ($roleIds as $roleId) {
                                    $role = \Spatie\Permission\Models\Role::find($roleId);
                                    if ($role && $role->name === 'owner') {
                                        return true;
                                    }
                                }
                                return false;
                            })
                            ->helperText('オーナーが管理できる複数店舗を選択'),
                    ]),

                Forms\Components\Section::make('プロフィール')
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('電話番号')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('status')
                            ->label('状態')
                            ->options([
                                'active' => '有効',
                                'inactive' => '無効',
                            ])
                            ->default('active')
                            ->required(),
                        Forms\Components\Toggle::make('is_available')
                            ->label('予約受付可能')
                            ->helperText('スタッフとして予約を受け付けるかどうか'),
                        Forms\Components\Textarea::make('bio')
                            ->label('自己紹介')
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('通知設定')
                    ->schema([
                        Forms\Components\Toggle::make('notification_preferences.email_enabled')
                            ->label('メール通知を受信')
                            ->helperText('予約作成・変更・キャンセル時にメールで通知を受け取る')
                            ->default(true)
                            ->reactive(),
                        Forms\Components\Toggle::make('notification_preferences.sms_enabled')
                            ->label('SMS通知を受信')
                            ->helperText('緊急時（キャンセル・変更）にSMSで通知を受け取る')
                            ->default(false),
                        Forms\Components\Select::make('notification_preferences.notification_types')
                            ->label('通知を受け取る予約操作')
                            ->multiple()
                            ->options([
                                'new_reservation' => '新規予約',
                                'cancellation' => 'キャンセル',
                                'change' => '予約変更',
                            ])
                            ->default(['new_reservation', 'cancellation', 'change'])
                            ->helperText('どの操作の時に通知を受け取るかを選択'),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('名前')
                    ->formatStateUsing(fn ($state) => mb_convert_encoding($state ?? '', 'UTF-8', 'auto'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('メールアドレス')
                    ->searchable(),
                Tables\Columns\TextColumn::make('store.name')
                    ->label('所属店舗')
                    ->formatStateUsing(fn ($state) => $state ?? '-')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.display_name')
                    ->label('ロール')
                    ->formatStateUsing(fn ($state) => $state ?? 'なし')
                    ->badge()
                    ->separator(','),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('状態')
                    ->colors([
                        'success' => 'active',
                        'danger' => 'inactive',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'active' => '有効',
                        'inactive' => '無効',
                        default => $state,
                    }),
                Tables\Columns\IconColumn::make('is_available')
                    ->label('予約受付')
                    ->boolean(),
                Tables\Columns\IconColumn::make('notification_preferences.email_enabled')
                    ->label('メール通知')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->notification_preferences['email_enabled'] ?? true)
                    ->toggleable(),
                Tables\Columns\IconColumn::make('notification_preferences.sms_enabled')
                    ->label('SMS通知')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->notification_preferences['sms_enabled'] ?? false)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('登録日')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('store_id')
                    ->label('店舗')
                    ->options(\App\Models\Store::where('is_active', true)->pluck('name', 'id'))
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('ロール')
                    ->relationship('roles', 'display_name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record->display_name ?? $record->name ?? 'Unknown'),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状態')
                    ->options([
                        'active' => '有効',
                        'inactive' => '無効',
                    ]),
                Tables\Filters\TernaryFilter::make('is_available')
                    ->label('予約受付可能'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
    
    public static function canViewAny(): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }
    
    public static function canCreate(): bool
    {
        // Filamentのauth guardを使用
        $user = \Filament\Facades\Filament::auth()->user();
        if (!$user) {
            return false;
        }
        
        return $user->hasRole(['super_admin', 'owner', 'manager']);
    }
    
    public static function canEdit($record): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->can('update', $record);
    }
    
    public static function canDelete($record): bool
    {
        $user = auth()->user();
        if (!$user || !$user->roles()->exists()) {
            return false;
        }
        
        return $user->can('delete', $record);
    }
    
    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();
        
        if (!$user || !$user->roles()->exists()) {
            return $query->whereRaw('1 = 0');
        }
        
        // スーパーアドミンは全ユーザーを表示
        if ($user->hasRole('super_admin')) {
            return $query;
        }
        
        // オーナーは管理可能店舗のユーザーを表示
        if ($user->hasRole('owner')) {
            $manageableStoreIds = $user->manageableStores()->pluck('stores.id');
            return $query->whereIn('store_id', $manageableStoreIds);
        }
        
        // 店長は同じ店舗のみ表示
        if ($user->hasRole('manager')) {
            return $query->where('store_id', $user->store_id);
        }
        
        // 権限がない場合は空のクエリ
        return $query->whereRaw('1 = 0');
    }
}