<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CustomerTicket;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

class CustomerTicketController extends Controller
{
    /**
     * 顧客の回数券一覧を取得
     */
    public function index(Request $request)
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization'));

        if (!$token) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $customer = null;

        // Sanctumトークンかどうかをチェック（パイプが含まれている場合）
        if (strpos($token, '|') !== false) {
            // Sanctumトークンの場合
            $personalAccessToken = \Laravel\Sanctum\PersonalAccessToken::findToken($token);
            if ($personalAccessToken) {
                $customer = $personalAccessToken->tokenable;
            }
        } else {
            // Base64エンコードされた顧客データの場合（従来の方式）
            $customerData = json_decode(base64_decode($token), true);
            $customerId = $customerData['id'] ?? null;

            if (!$customerId) {
                // トークンから取得できない場合、電話番号で検索（テスト用）
                $phone = $customerData['phone'] ?? null;
                if ($phone) {
                    $customer = Customer::where('phone', $phone)->first();
                }
            } else {
                $customer = Customer::find($customerId);
            }
        }

        if (!$customer) {
            return response()->json(['error' => 'Invalid token'], 401);
        }

        // 顧客の全回数券を取得（有効・期限切れ・使い切り全て）
        $tickets = CustomerTicket::where('customer_id', $customer->id)
            ->with(['ticketPlan', 'store'])
            ->orderByRaw("CASE
                WHEN status = 'active' THEN 1
                WHEN status = 'expired' THEN 2
                WHEN status = 'used_up' THEN 3
                ELSE 4 END")
            ->orderByRaw('expires_at IS NULL') // 無期限を最後に
            ->orderBy('expires_at', 'asc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($ticket) {
                return [
                    'id' => $ticket->id,
                    'plan_name' => $ticket->plan_name,
                    'store_name' => $ticket->store->name ?? '',
                    'total_count' => $ticket->total_count,
                    'used_count' => $ticket->used_count,
                    'remaining_count' => $ticket->remaining_count,
                    'status' => $ticket->status,
                    'status_label' => match ($ticket->status) {
                        'active' => '有効',
                        'expired' => '期限切れ',
                        'used_up' => '使い切り',
                        'cancelled' => 'キャンセル',
                        default => $ticket->status,
                    },
                    'purchased_at' => $ticket->purchased_at?->format('Y年m月d日'),
                    'expires_at' => $ticket->expires_at?->format('Y年m月d日'),
                    'is_expired' => $ticket->is_expired,
                    'is_expiring_soon' => $ticket->is_expiring_soon,
                    'days_until_expiry' => $ticket->days_until_expiry,
                    'purchase_price' => $ticket->purchase_price,
                ];
            });

        // 統計情報
        $stats = [
            'total_tickets' => $tickets->count(),
            'active_tickets' => $tickets->where('status', 'active')->count(),
            'total_remaining' => $tickets->where('status', 'active')->sum('remaining_count'),
            'expiring_soon' => $tickets->where('is_expiring_soon', true)->count(),
        ];

        return response()->json([
            'tickets' => $tickets,
            'stats' => $stats,
            'customer' => [
                'name' => $customer->full_name,
            ],
        ]);
    }

    /**
     * 回数券の利用履歴を取得
     */
    public function history(Request $request, $ticketId)
    {
        // Sanctum認証を使用
        $customer = $request->user();

        if (!$customer) {
            return response()->json(['error' => '認証が必要です'], 401);
        }

        // 自分の回数券かチェック
        $ticket = CustomerTicket::where('id', $ticketId)
            ->where('customer_id', $customer->id)
            ->first();

        if (!$ticket) {
            return response()->json(['error' => '回数券が見つかりません'], 404);
        }

        // 利用履歴を取得
        $history = $ticket->usageHistory()
            ->with('reservation.menu')
            ->orderBy('used_at', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'used_at' => $item->used_at->format('Y年m月d日 H:i'),
                    'used_count' => $item->used_count,
                    'is_cancelled' => $item->is_cancelled,
                    'cancelled_at' => $item->cancelled_at?->format('Y年m月d日 H:i'),
                    'cancel_reason' => $item->cancel_reason,
                    'reservation' => $item->reservation ? [
                        'id' => $item->reservation->id,
                        'reservation_number' => $item->reservation->reservation_number,
                        'reservation_date' => $item->reservation->reservation_date->format('Y年m月d日'),
                        'start_time' => substr($item->reservation->start_time, 0, 5),
                        'menu_name' => $item->reservation->menu->name ?? '',
                    ] : null,
                ];
            });

        return response()->json([
            'ticket' => [
                'id' => $ticket->id,
                'plan_name' => $ticket->plan_name,
                'total_count' => $ticket->total_count,
                'used_count' => $ticket->used_count,
                'remaining_count' => $ticket->remaining_count,
            ],
            'history' => $history,
        ]);
    }

    /**
     * 回数券ページのビューを表示
     */
    public function show()
    {
        return view('customer.tickets');
    }
}
