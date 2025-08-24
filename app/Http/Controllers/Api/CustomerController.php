<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Customer $customer)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Customer $customer)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Customer $customer)
    {
        //
    }

    /**
     * 顧客のカルテ（医療記録）取得
     */
    public function getMedicalRecords(Request $request)
    {
        $customer = $request->user();
        
        $medicalRecords = $customer->medicalRecords()
            ->with(['reservation.store', 'reservation.menu', 'createdBy'])
            ->orderBy('record_date', 'desc')
            ->get();

        return response()->json([
            'message' => 'カルテを取得しました',
            'data' => $medicalRecords
        ]);
    }
}
