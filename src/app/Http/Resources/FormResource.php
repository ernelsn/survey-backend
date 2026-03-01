<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class FormResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $content = $this->additional['content'] ?? 'private';
    
        // If content is public but accept_response is false, return only title and accept_response
        if ($content === 'public' && !$this->accept_response) {
            return [
                'title' => $this->title,
                'accept_response' => (bool)$this->accept_response,
            ];
        }
    
        // For all other cases (content is public and accept_response is true, or content is not public), return all attributes
        $originalUrl = $this->image ? URL::to($this->image) : null;
        $webpUrl = null;
       
        if ($originalUrl) {
            $webpPath = str_replace(
                ['.jpg', '.jpeg', '.png'],
                '.webp',
                str_replace('storage/', '', $this->image)
            );
           
            if (Storage::disk('public')->exists($webpPath)) {
                $webpUrl = URL::to('storage/' . $webpPath);
            }
        }
    
        return [
            'id' => $this->id,
            'image_url' => $originalUrl,
            'image_webp_url' => $webpUrl,
            'title' => $this->title,
            'slug' => $this->slug,
            'is_published' => (bool)$this->is_published,
            'accept_response' => (bool)$this->accept_response,
            'is_quiz' => (bool)$this->is_quiz,
            'show_results' => (bool)$this->show_results,
            'multiple_attempts' => (bool)$this->multiple_attempts,
            'default_points' => $this->default_points,
            'description' => $this->description,
            'created_at' => (new \DateTime($this->created_at))->format('Y-m-d'),
            'updated_at' => (new \DateTime($this->updated_at))->format('Y-m-d'),
            'expire_date' => $this->expire_date ? (new \DateTime($this->expire_date))->format('Y-m-d') : null,
            'time_limit' => $this->time_limit,
            'sections' => $this->formSections->map(function ($section) use ($content) {
                return [
                    'id' => $section->id,
                    'title' => $section->title,
                    'description' => $section->description,
                    'questions' => $section->formSectionQuestions->map(function ($question) use ($content) {
                        return (new FormQuestionResource($question))->additional(['content' => $content]);
                    }),
                ];
            })
        ];
    }
}
