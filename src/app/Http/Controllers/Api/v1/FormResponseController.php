<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFormResponseRequest;
use App\Models\Form;
use App\Models\FormSectionQuestion;
use App\Models\FormResponseQuestion;
use App\Models\FormResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormResponseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Form $form)
    {
        $formResponses = $form->formResponses()->with('user')->get();
    
        $responseData = $formResponses->map(function ($response) {
            return [
                'id' => $response->id,
                'name' => $response->user->name,
            ];
        });
    
        return [
            'form_responses_count' => $formResponses->count(),
            'form_responses' => $responseData,
            'is_accepting' => $form->accept_response,
        ];
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormResponseRequest $request, Form $form)
    {
        $validated = $request->validated();
       
        DB::beginTransaction();
        try {
            $formResponse = FormResponse::create([
                'user_id' => auth()->user()->id,
                'form_id' => $form->id,
                'start_date' => date('Y-m-d H:i:s'),
                'end_date' => date('Y-m-d H:i:s'),
            ]);
    
            foreach ($validated['responses'] as $response) {
                $question_id = $response['questionId'];
                $section_id = $response['sectionId'];
                $answer = $response['response'] ?? null;
                $question = FormSectionQuestion::where([
                    'id' => $question_id,
                    'form_section_id' => $section_id
                ])->first();
    
                if (!$question) {
                    throw new \Exception("Invalid question ID: \"$question_id\" in section ID: \"$section_id\"");
                }
    
                $data = json_decode($question->data, true);
                $is_correct = null;
                if ($question->type == 'checkbox' || $question->type == 'multiple choice') {
                    $correct_answers = array_filter($data['options'], function($option) {
                        return $option['is_correct'] ?? false;
                    });
                    $correct_answers_texts = array_column($correct_answers, 'text');
                    if (is_array($answer)) {
                        $is_correct = !array_diff($correct_answers_texts, $answer) && !array_diff($answer, $correct_answers_texts);
                    } else {
                        $is_correct = in_array($answer, $correct_answers_texts) && count($correct_answers_texts) == 1;
                    }
                }
    
                $response_data = [
                    'form_response_id' => $formResponse->id,
                    'form_section_id' => $section_id,
                    'form_section_question_id' => $question_id,
                    'response' => $answer !== null ? json_encode(is_array($answer) ? $answer : [$answer]) : null,
                    'is_correct' => $is_correct
                ];
                FormResponseQuestion::create($response_data);
            }
    
            DB::commit();
            return response('', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Form store error', [
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            return response()->json([
                'error' => 'An error occurred while saving the form',
                // 'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Form $form, FormResponse $response)
    {
        if ($response->form_id !== $form->id) {
            return response()->json(['error' => 'Form response does not belong to the specified form'], 403);
        }
    
        $response->delete();
    
        return response()->json(['message' => 'Form response deleted successfully'], 200);
    }

    public function showResults(Form $form)
    {
        $user = auth()->user()->id;
        
        $latestFormResponse = FormResponse::where('user_id', $user)
                                          ->where('form_id', $form->id)
                                          ->latest()
                                          ->first();
        
        if (!$latestFormResponse) {
            return response()->json(['message' => 'No response found for this form'], 404);
        }
        
        $sections = $form->formSections()->with('formSectionQuestions')->get();
        
        $results = $sections->map(function ($section) use ($latestFormResponse) {
            $sectionQuestions = $section->formSectionQuestions;
            
            $totalQuestions = $sectionQuestions->count();
            $totalPoints = $sectionQuestions->sum('points');
            
            $correctResponses = FormResponseQuestion::whereIn('form_section_question_id', $sectionQuestions->pluck('id'))
                                                    ->where('form_response_id', $latestFormResponse->id)
                                                    ->where('is_correct', true)
                                                    ->get();
            
            $correctCount = $correctResponses->count();
            $correctPoints = $correctResponses->sum(function ($response) {
                return $response->formSectionQuestion->points;
            });
            
            return [
                // 'section_id' => $section->id,
                'section_title' => $section->title,
                'total_questions' => $totalQuestions,
                'total_points' => $totalPoints,
                'correct_responses' => $correctCount,
                'correct_points' => $correctPoints,
            ];
        });
        
        $overallResults = [
            'total_questions' => $sections->sum(function ($section) {
                return $section->formSectionQuestions->count();
            }),
            'total_points' => $sections->sum(function ($section) {
                return $section->formSectionQuestions->sum('points');
            }),
            'total_correct_responses' => FormResponseQuestion::where('form_response_id', $latestFormResponse->id)
                                                             ->where('is_correct', true)
                                                             ->count(),
            'total_correct_points' => FormResponseQuestion::where('form_response_id', $latestFormResponse->id)
                                                          ->where('is_correct', true)
                                                          ->get()
                                                          ->sum(function ($response) {
                                                              return $response->formSectionQuestion->points;
                                                          }),
        ];
        
        return [
            'overall_results' => $overallResults,
            'section_results' => $results,
        ];
    }
    
}
