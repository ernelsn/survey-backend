<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Form extends Model
{
    use HasFactory, HasSlug;

    const TYPE_SHORT_ANSWER = 'short answer';
    const TYPE_PARAGRAPH = 'paragraph';
    const TYPE_LINEAR_SCALE = 'linear scale';
    const TYPE_MULTIPLE_CHOICE = 'multiple choice';
    const TYPE_CHECKBOX = 'checkbox';
    const TYPE_DROPDOWN = 'dropdown';

    protected $fillable = [
        'user_id', 
        'image', 
        'title', 
        'slug', 
        'description', 
        'time_limit', 
        'expire_date',
        'is_published',
        'accept_response',
        'is_quiz',
        'default_points',
        'show_results',
        'multiple_attempts'
    ];

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug');
    }

    public function formSections()
    {
        return $this->hasMany(FormSection::class);
    }

    public function formResponses()
    {
        return $this->hasMany(FormResponse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
