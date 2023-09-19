<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class AvitoController extends Controller
{
//    public function index(Request $request)
//    {
//        return view('positions');
//    }

    public function run(Request $request)
    {
        $key = '79JqPg7CErReg4pr';

        if ($request->input('key') === $key) {
            Artisan::call('parsing:avito-positions');
            return response()->json(
                [
                    "success" => true,
                    "start" => now('MSK')->format('Y-m-d H:i:s'),
                    "end" => now('MSK')->addMinutes(5)->format('Y-m-d H:i:s'),
                ]
            );
        } else {
            return response()->json(
                [
                    "success" => false,
                ]
            );
        }
    }
}
