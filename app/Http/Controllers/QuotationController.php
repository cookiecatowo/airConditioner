<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Quotation; // 確保有這行
use Inertia\Inertia;      // 確保有這行

class QuotationController extends Controller
{
    /**
     * 1. 顯示新增頁面 (這就是報錯說找不到的那個！)
     */
    public function create()
    {
        return Inertia::render('Quotations/Create');
    }

    /**
     * 2. 儲存資料
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'ac_model' => 'required|string',
            'price' => 'required|integer',
            'quantity' => 'required|integer',
            'notes' => 'nullable|string',
        ]);

        $validated['total_amount'] = $request->price * $request->quantity;
        
        Quotation::create($validated);

        return redirect()->back();
    }
}
