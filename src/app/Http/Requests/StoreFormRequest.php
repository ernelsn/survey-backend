<?php

namespace App\Http\Requests;

use App\Models\Form;
use App\Models\FormSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'user_id' => $this->user()->id
        ]);
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
            'default_points' => 'nullable|numeric',
            'is_published' => 'nullable|boolean',
            'is_quiz' => 'nullable|boolean',
            'show_results' => 'nullable|boolean',
            'multiple_attempts' => 'nullable|boolean',
            'sections' => 'required|array',
            'sections.*.title' => 'sometimes|nullable|string|max:1000',
            'sections.*.description' => 'sometimes|nullable|string',
            'sections.*.questions' => 'present|array',
            'sections.*.questions.*.form_section_id' => ['sometimes', 'required_with:sections', Rule::exists(FormSection::class, 'id')],
            'sections.*.questions.*.question' => 'sometimes|required|string',
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
            'sections.*.questions.*.points' => 'sometimes|nullable|numeric',
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
