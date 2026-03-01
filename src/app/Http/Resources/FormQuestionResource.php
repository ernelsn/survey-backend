<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FormQuestionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $originalUrl = Str::startsWith($this->description, 'storage/') ? URL::to($this->description) : null;
        $webpUrl = null;
        
        if ($originalUrl) {
            $webpPath = str_replace(
                ['.jpg', '.jpeg', '.png'],
                '.webp',
                str_replace('storage/', '', $this->description)
            );
            
            if (Storage::disk('public')->exists($webpPath)) {
                $webpUrl = URL::to('storage/' . $webpPath);
            }
        }

        $data = json_decode($this->data, true);
        $content = $this->additional['content'] ?? 'private';
    
        if ($content === 'public') {
            if (isset($data['options']) && is_array($data['options'])) {
                foreach ($data['options'] as &$option) {
                    unset($option['is_correct']);
                }
                unset($option);
            }
        }
    
        return [
            'id' => $this->id,
            'type' => $this->type,
            'question' => $this->question,
            'description' => Str::startsWith($this->description, 'storage/') ? "" : $this->description,
            'description_url' => $originalUrl,
            'description_webp_url' => $webpUrl,
            'points' => $this->points,
            'data' => $data,
            'is_required' => (bool)$this->is_required,
        ];
    }
}
