<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardSurveyResource;
use App\Models\FormResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();
    
        // Eager load all the necessary relationships
        $userForms = $user->forms()->withCount('formResponses')->get();
    
        $totalForms = $userForms->count();
        $latestForm = $userForms->sortByDesc('created_at')->first();
        $totalFormResponse = $userForms->sum('form_responses_count');
    
        $latestFormResponses = FormResponse::whereIn('form_id', $userForms->pluck('id'))
            ->with(['form.formSections.formSectionQuestions', 'formResponseQuestions'])
            ->latest('end_date')
            ->take(5)
            ->get()
            ->map(function ($response) {
                $form = $response->form;
                $totalQuestions = $form->formSections->sum(function ($section) {
                    return $section->formSectionQuestions->count();
                });
                $correctAnswers = $response->formResponseQuestions->where('is_correct', true)->count();
    
                return [
                    'id' => $response->id,
                    'form_id' => $response->form_id,
                    'form_title' => $form->title,
                    'end_date' => $response->end_date,
                    'total_questions' => $totalQuestions,
                    'correct_answers' => $correctAnswers,
                ];
            });
    
        return [
            'totalForms' => $totalForms,
            'latestForm' => $latestForm ? new DashboardSurveyResource($latestForm) : null,
            'totalFormResponse' => $totalFormResponse,
            'latestFormResponses' => $latestFormResponses
        ];
    }
    
}
