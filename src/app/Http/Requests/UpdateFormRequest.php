<?php

namespace App\Http\Requests;

use App\Models\Form;
use App\Models\FormSectionQuestion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $form = $this->route('form');
        if ($this->user()->id !== $form->user_id) {
            return false;
        }
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:1000',
            'image' => 'nullable',
            'user_id' => 'exists:users,id',
            'description' => 'nullable|string',
            'expire_date' => 'nullable|date|after:tomorrow',
            'time_limit' => 'nullable|numeric',
            'is_published' => 'nullable|boolean',
            'is_quiz' => 'nullable|boolean',
            'show_results' => 'nullable|boolean',
            'default_points' => 'nullable|numeric',
            'multiple_attempts' => 'nullable|boolean',
            'sections' => 'required|array',
            'sections.*.id' => 'nullable|integer',
            'sections.*.title' => 'nullable|string|max:1000',
            'sections.*.description' => 'nullable|string',
            'sections.*.questions' => 'present|array',
            'sections.*.questions.*.id' => [
                'sometimes',
                // 'exclude_if:sections.*.questions.*.id,null',
                function ($value) {
                    if (!is_null($value) && !is_string($value)) {
                        FormSectionQuestion::where('id', $value)->exists();
                    }
                },
            ],
            'sections.*.questions.*.question' => 'required|string',
            'sections.*.questions.*.type' => [
                'required',
                Rule::in([
                    Form::TYPE_SHORT_ANSWER,
                    Form::TYPE_PARAGRAPH,
                    Form::TYPE_LINEAR_SCALE,
                    Form::TYPE_MULTIPLE_CHOICE,
                    Form::TYPE_CHECKBOX,
                    Form::TYPE_DROPDOWN,
                ]),
            ],
            'sections.*.questions.*.description' => 'sometimes|nullable',
            'sections.*.questions.*.points' => 'nullable|numeric',
            'sections.*.questions.*.data' => 'present',
            'sections.*.questions.*.is_required' => 'sometimes|nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'sections.*.questions.*.question' => 'This field is required.',
            'sections.*.questions.*.type' => 'This field is required.',
        ];
    }
}
