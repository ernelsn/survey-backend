<?php

namespace App\Http\Requests;

use App\Models\FormSectionQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'responses' => 'sometimes|nullable|array',
            'responses.*.sectionId' => 'required|integer',
            'responses.*.questionId' => 'required|integer',
            'responses.*.response' => 'nullable',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $responses = $this->input('responses', []);
            
            foreach ($responses as $index => $response) {
                $questionId = $response['questionId'] ?? null;
                $question = FormSectionQuestion::find($questionId);
                
                if ($question && $question->is_required && empty($response['response'])) {
                    $validator->errors()->add(
                        "responses.$index.response",
                        "This question is required"
                    );
                }
            }
        });
    }
}
