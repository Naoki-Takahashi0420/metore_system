<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Announcement;
use App\Models\User;

class AnnouncementSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // スーパーアドミンユーザーを取得
        $superAdmin = User::whereHas('roles', function($query) {
            $query->where('name', 'super_admin');
        })->first();

        if (!$superAdmin) {
            $this->command->warn('スーパーアドミンユーザーが見つかりません。お知らせを作成できません。');
            return;
        }

        // テストお知らせを作成
        $announcements = [
            [
                'title' => 'システムメンテナンスのお知らせ',
                'content' => '<p>下記の日程でシステムメンテナンスを実施いたします。</p><p><strong>日時：</strong> 2025年10月25日（金） 22:00〜23:00</p><p><strong>影響範囲：</strong> 予約システム全体が一時的に利用できなくなります。</p><p>ご不便をおかけいたしますが、ご理解とご協力をお願いいたします。</p>',
                'priority' => 'urgent',
                'target_type' => 'all',
                'published_at' => now()->subDays(2),
                'expires_at' => now()->addDays(5),
                'is_active' => true,
            ],
            [
                'title' => '新機能リリースのお知らせ',
                'content' => '<p>お客様の利便性向上のため、以下の新機能をリリースいたしました。</p><ul><li>カルテ編集機能の追加</li><li>予約詳細画面の情報拡充</li><li>本部からのお知らせ機能</li></ul><p>ご不明な点がございましたら、本部までお問い合わせください。</p>',
                'priority' => 'important',
                'target_type' => 'all',
                'published_at' => now()->subDays(1),
                'expires_at' => null,
                'is_active' => true,
            ],
            [
                'title' => '年末年始の営業について',
                'content' => '<p>年末年始の営業スケジュールをお知らせいたします。</p><p><strong>休業期間：</strong> 12月30日（土）〜1月3日（水）</p><p><strong>営業再開：</strong> 1月4日（木）より通常営業</p><p>お客様には大変ご迷惑をおかけいたしますが、何卒ご理解のほどお願い申し上げます。</p>',
                'priority' => 'normal',
                'target_type' => 'all',
                'published_at' => now()->subHours(12),
                'expires_at' => now()->addDays(30),
                'is_active' => true,
            ],
            [
                'title' => '操作マニュアル更新のご案内',
                'content' => '<p>システムの操作マニュアルを更新いたしました。</p><p>最新版は共有フォルダからダウンロード可能です。主な変更点は以下の通りです：</p><ul><li>予約変更手順の詳細化</li><li>カルテ作成のフロー図追加</li><li>よくある質問のセクション追加</li></ul>',
                'priority' => 'normal',
                'target_type' => 'all',
                'published_at' => now()->subHours(6),
                'expires_at' => null,
                'is_active' => true,
            ],
            [
                'title' => '研修会開催のお知らせ',
                'content' => '<p>スタッフ向けの研修会を下記の通り開催いたします。</p><p><strong>日時：</strong> 11月15日（水） 14:00〜16:00</p><p><strong>場所：</strong> 本部会議室</p><p><strong>内容：</strong> 新システム機能の使い方、接客スキル向上</p><p>参加希望の方は11月10日までに本部までご連絡ください。</p>',
                'priority' => 'important',
                'target_type' => 'all',
                'published_at' => now()->subHours(2),
                'expires_at' => now()->addDays(20),
                'is_active' => true,
            ],
        ];

        foreach ($announcements as $data) {
            $announcement = Announcement::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'priority' => $data['priority'],
                'target_type' => $data['target_type'],
                'published_at' => $data['published_at'],
                'expires_at' => $data['expires_at'],
                'is_active' => $data['is_active'],
                'created_by' => $superAdmin->id,
            ]);

            $this->command->info("お知らせを作成しました: {$announcement->title}");
        }

        $this->command->info('テストお知らせデータの作成が完了しました。');
    }
}
