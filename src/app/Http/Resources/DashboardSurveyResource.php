<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Nette\Utils\DateTime;

class DashboardSurveyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
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
            'is_published' => (bool)$this->is_published,
            'created_at' => (new DateTime($this->created_at))->format('Y-m-d'),
            'expire_date' => $this->expire_date,
            'total_questions' => $this->formSections->sum(function ($section) {
                return $section->formSectionQuestions->count();
            }),
            'responses' => $this->formResponses()->count()
        ];
    }
}
