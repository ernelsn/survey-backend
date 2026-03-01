<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FormSectionQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'form_section_id',
        'type',
        'question',
        'description',
        'points',
        'data',
        'is_required'
    ];

    public function formSection()
    {
        return $this->belongsTo(FormSection::class);
    }
}
