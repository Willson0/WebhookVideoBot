<?php

namespace App\Http\Controllers;

use App\Models\PerplexityPrompt;
use Illuminate\Http\Request;

class ViewerController extends Controller
{
    public function get ($hash, Request $request) {
        $row = PerplexityPrompt::where("hash", $hash)->first();
        if (!$row || $row->user_id !== $request["initData"]["user"]["id"]) abort (404);

        $row->sources = json_decode($row->sources);
        return response()->json($row);
    }
}
