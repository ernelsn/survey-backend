<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormResponseQuestion extends Model
{
    use HasFactory;

    protected $fillable = ['form_response_id', 'form_section_id', 'form_section_question_id', 'response', 'is_correct'];

    public function formResponse() {
        return $this->belongsTo(FormResponse::class);
    }

    public function formSection()
    {
        return $this->belongsTo(FormSection::class);
    }

    public function formSectionQuestion() {
        return $this->belongsTo(FormSectionQuestion::class);
    }
}
