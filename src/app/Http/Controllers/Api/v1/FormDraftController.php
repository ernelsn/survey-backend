<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\FormDraft;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormDraftRequest;
use App\Http\Requests\UpdateFormDraftRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class FormDraftController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $drafts = FormDraft::where('user_id', Auth::id())->get();
        return response()->json($drafts);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormDraftRequest $request)
    {
        $data = $request->validated();

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

        $draft = FormDraft::create($data + ['user_id' => Auth::id()]);
    
        return response()->json($draft, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(FormDraft $draft)
    {
        $draft = FormDraft::where('id', $draft->id)->first();

        if (!$draft) {
            return response()->json(['message' => 'Draft not found'], 404);
        }

        return response()->json($draft);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormDraftRequest $request, FormDraft $draft)
    {
        $data = $request->validated();

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

        $formDraft = FormDraft::findOrFail($draft->id);
        
        if ($formDraft->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }
    
        $formDraft->update($data);
    
        return response()->json($formDraft);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(FormDraft $draft)
    {
        $draft = FormDraft::where('id', $draft->id)->first();

        if (!$draft) {
            return response()->json(['message' => 'Draft not found'], 404);
        }

        $draft->delete();

        return response()->json(['message' => 'Draft deleted'], 200);
    }
}
