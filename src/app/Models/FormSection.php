<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSection extends Model
{
    use HasFactory;

    protected $fillable = ['form_id', 'title', 'description'];

    public function form()
    {
        return $this->belongsTo(Form::class);
    }

    public function formSectionQuestions()
    {
        return $this->hasMany(FormSectionQuestion::class);
    }
}
