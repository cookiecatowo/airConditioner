<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Imports\DaikinProductsImport;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function index()
    {
        return Inertia::render('ImportDaikin'); 
    }

    public function import(Request $request)
    {
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls',
        ]);

        $importer = new DaikinProductsImport();
        $count = $importer->import($request->file('excel_file')->getPathname());

        return redirect()->route('import.daikin.index')->with('message', "匯入完成，共更新 {$count} 筆大金機型資料！");
    }
}