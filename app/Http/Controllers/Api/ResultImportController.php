<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Results\ResultImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResultImportController extends Controller
{
    public function store(Request $request, ResultImporter $importer): JsonResponse
    {
        $data = $request->validate(['rows' => ['required', 'array', 'max:2000']]);

        return response()->json($importer->import($data['rows']));
    }
}
