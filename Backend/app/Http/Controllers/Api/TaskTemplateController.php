<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskTemplate;
use Illuminate\Http\Request;

class TaskTemplateController extends Controller
{
    public function index()
    {
        return response()->json(TaskTemplate::orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tasks' => ['required', 'array', 'min:1'],
            'tasks.*' => ['required', 'string', 'max:255'],
        ]);

        $template = TaskTemplate::create($data);

        return response()->json($template, 201);
    }
}
