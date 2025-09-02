<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Models\Customer;
use Illuminate\Http\Request;

class MedicalRecordController extends Controller
{
    /**
     * 顧客向け視力推移表示
     */
    public function showVisionProgress($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        
        // 最新のカルテを取得
        $latestRecord = MedicalRecord::where('customer_id', $customerId)
            ->whereNotNull('vision_records')
            ->latest('treatment_date')
            ->first();
        
        // 初回来店日
        $firstVisit = MedicalRecord::where('customer_id', $customerId)
            ->oldest('treatment_date')
            ->value('treatment_date') ?? now();
        
        return view('customer.vision-progress', compact('customer', 'latestRecord', 'firstVisit'));
    }
    
    /**
     * カルテ印刷用ビュー
     */
    public function print(MedicalRecord $record)
    {
        $customer = $record->customer;
        $publicData = $record->getPublicData();
        
        return view('medical-record.print', compact('record', 'customer', 'publicData'));
    }
}