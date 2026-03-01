<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\Form;
use App\Models\FormSectionQuestion;
use App\Http\Requests\StoreFormRequest;
use App\Http\Requests\UpdateFormRequest;
use App\Http\Resources\FormResource;
use App\Models\TemporaryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class FormController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        return FormResource::collection(Form::where('user_id', $user->id)->orderBy('created_at', 'DESC')->paginate(10));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreFormRequest $request)
    {
        $data = $request->validated();
        DB::beginTransaction();
        try {
            if (!empty($data['image'])) {
                $data = $this->processImage($data, 'image');
            } else {
                $data['image'] = null;
            }
            
            $form = Form::create($data);
            
            foreach ($data['sections'] as $section) {
                $formSection = $form->formSections()->create([
                    'form_id' => $form->id,
                    'title' => $section['title'] ?? null,
                    'description' => $section['description'] ?? null,
                ]);
                
                foreach ($section['questions'] as $question) {
                    $image = TemporaryFile::where('folder', $question['description'])->first();
                    if ($image) {
                        $imageData = $this->processImage(['description' => $question['description']], 'description');
                        if ($imageData && isset($imageData['description'])) {
                            $question['description'] = $imageData['description'];
                        }
                    }
                    
                    $questionData = [
                        'form_section_id' => $formSection->id,
                        'question' => $question['question'],
                        'type' => $question['type'],
                        'description' => $question['description'] ?? null,
                        'points' => $question['points'] ?? null,
                        'data' => is_array($question['data']) ? json_encode($question['data']) : $question['data'],
                        'is_required' => $question['is_required'] ?? null,
                    ];
                    
                    $this->createQuestion($questionData);
                }
            }
            
            DB::commit();
            return new FormResource($form->refresh());
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Form store error', [
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            return response()->json([
                'error' => 'An error occurred while saving the form',
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Form $form, Request $request)
    {
        $user = $request->user();
        if ($user->id !== $form->user_id) {
            return abort(403, 'Unauthorized action.');
        }

        return new FormResource($form->refresh());
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateFormRequest $request, Form $form)
    {
        // TODO: Optimize this huge update function;
        $data = $request->validated();

        DB::beginTransaction();
        try {
            if (!empty($data['image'])) {
                $data = $this->processImage($data, 'image', $form);
            }
            if(!$data['is_quiz'] ) {
                $data['time_limit'] = null;
                $data['show_results'] = false;
                $data['default_points'] = null;
            }

            if(!$data['is_published']) {
                $data['expire_date'] = null;
            }
            $form->update($data);

            $updatedSectionIds = [];
            foreach ($data['sections'] as $section) {
                $formSection = $form->formSections()->updateOrCreate(
                    ['id' => $section['id'] ?? null],
                    [
                        'title' => $section['title'],
                        'description' => $section['description'],
                    ]
                );
    
                $updatedSectionIds[] = $formSection->id;

                $existingQuestions = $formSection->formSectionQuestions->pluck('id')->toArray();
                $newQuestions = Arr::pluck($section['questions'], 'id');
            
                $toBeDeleted = array_diff($existingQuestions, $newQuestions);
                $toBeAdded = array_diff($newQuestions, $existingQuestions);
            
                // Delete questions along with its images
                $deleteImages = FormSectionQuestion::whereIn('id', $toBeDeleted)->get();
                foreach ($deleteImages as $questionImage) {
                    $this->handleOldImage($questionImage->description);
                }
                FormSectionQuestion::destroy($toBeDeleted);
            
                // Create new questions
                foreach ($section['questions'] as $question) {
                    if (in_array($question['id'], $toBeAdded)) {
                        $image = TemporaryFile::where('folder', $question['description'])->first();
                        if($image) {
                            $imageData = $this->processImage(['description' => $question['description']], 'description', null, $question['description']);
                            if ($imageData && isset($imageData['description'])) {
                                $question['description'] = $imageData['description'];
                            }
                        }
                        $questionData = [
                            'form_section_id' => $formSection->id,
                            'question' => $question['question'],
                            'type' => $question['type'],
                            'description' => $question['description'] ?? null,
                            'points' => $question['points'],
                            'data' => $question['data'] ?? null,
                            'is_required' => $question['is_required'] ?? null,
                        ];
                        $this->createQuestion($questionData);
                    }
                }
            
                // Update existing questions
                $questionMap = collect($section['questions'])->keyBy('id');
                $updatedQuestions = [];
                
                foreach ($formSection->formSectionQuestions as $question) {
                    if (isset($questionMap[$question->id])) {
                        $updatedData = $questionMap[$question->id];

                        // Use default_points if the question's points are different
                        if ($data['is_quiz'] && isset($data['default_points']) && $question->points != $data['default_points']) {
                            $updatedData['points'] = $data['default_points'];
                        }

                        // Fetch the existing question data if the description is null
                        if (is_null($updatedData['description'])) {
                            $updatedData['description'] = $question->description;
                        }
                
                        // Process the new image or text description
                        if (!empty($updatedData['description'])) {
                            $image = TemporaryFile::where('folder', $updatedData['description'])->first();
                            if ($image != null) {
                                $imageData = $this->processImage(['description' => $updatedData['description']], 'description', null, $updatedData['description']);
                                if ($imageData && isset($imageData['description'])) {
                                    $updatedData['description'] = $imageData['description'];
                                }
                            }

                            // Delete the old image if a new image is uploaded or the description is updated to text
                            if ($question->description && strpos($question->description, 'storage/images/') !== false && 
                                ($image != null || strpos($updatedData['description'], 'storage/images/') === false)) {
                                $this->handleOldImage($question->description);
                            }
                        }
                
                        $updatedQuestions[$question->id] = $updatedData;
                    }
                }
                
                // Update the questions
                foreach ($updatedQuestions as $questionId => $updatedData) {
                    $question = $formSection->formSectionQuestions->find($questionId);
                    if ($question) {
                        $this->updateQuestion($question, $updatedData);
                    }
                }
            }

            $sectionsToDelete = $form->formSections()->whereNotIn('id', $updatedSectionIds)->get();
            foreach ($sectionsToDelete as $sectionToDelete) {
                $questionsToDelete = $sectionToDelete->formSectionQuestions;
                foreach ($questionsToDelete as $questionToDelete) {
                    if ($questionToDelete->description && strpos($questionToDelete->description, 'storage/images/') !== false) {
                        $this->handleOldImage($questionToDelete->description);
                    }
                }
            }
            $form->formSections()->whereNotIn('id', $updatedSectionIds)->delete();
            DB::commit();
            return new FormResource($form->refresh());
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Form update error', [
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            return response()->json([
                'error' => 'An error occurred while updating the form',
                // 'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Form $form, Request $request)
    {
        $user = $request->user();
        if ($user->id !== $form->user_id) {
            return abort(403, 'Unauthorized action.');
        }

        DB::beginTransaction();
        try {
            if ($form->image) {
                $this->handleOldImage($form->image);
            }

            $form->delete();
            DB::commit();
            return response('', 204);
        }
        catch (\Exception $e) {
            DB::rollBack();
            // Log::error('Form delete error', [
            //     'message' => $e->getMessage(),
            //     'file' => $e->getFile(),
            //     'line' => $e->getLine(),
            //     'trace' => $e->getTraceAsString()
            // ]);
            return response()->json([
                'error' => 'An error occurred while deleting the form',
                // 'message' => $e->getMessage()
            ], 500);
        }
    }

    public function viewFormPublic(Form $form)
    {
        if (!$form->is_published) {
            return response()->json([
                'message' => 'Form not found',
                'title' => $form->title
            ], 404);
        }
    
        $currentDate = now();
        $expireDate = new \DateTime($form->expire_date);
        if ($currentDate > $expireDate) {
            return response()->json([
                'message' => 'Form has expired',
                'title' => $form->title
            ], 410);
        }
    
        $user = auth()->user();
    
        if (!$form->multiple_attempts) {
            $hasResponse = $form->formResponses()
                ->where('user_id', $user->id)
                ->exists();
    
            if ($hasResponse) {
                return response()->json([
                    'message' => 'You have already submitted a response to this form.',
                    'title' => $form->title
                ], 403);
            }
        }
    
        return (new FormResource($form))->additional(['content' => 'public']);
    }

    private function createQuestion($data)
    {
        // Remove id if it's present in the data
        unset($data['id']);

        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }
        
        return FormSectionQuestion::create($data);
    }

    private function updateQuestion(FormSectionQuestion $question, $data)
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = json_encode($data['data']);
        }

        return $question->update($data);
    }

    private function processImage($data, $key, $form = null, $question = null)
    {
        if (!isset($data[$key]) || empty($data[$key])) {
            return $data;
        }

        $folders = is_array($data[$key]) ? $data[$key] : [$data[$key]];
        $temporaryImages = TemporaryFile::whereIn('folder', $folders)->get();

        foreach ($temporaryImages as $temporaryImage) {
            $originalNewPath = $this->moveFile($temporaryImage->folder, $temporaryImage->file);
            if ($temporaryImage->webp_file) {
                $this->moveFile($temporaryImage->folder, $temporaryImage->webp_file);
            }

            if ($form && $key === 'image') {
                $this->handleOldImage($form->image);
            }

            if ($question && $key === 'description') {
                $this->handleOldImage($question);
            }

            // Update the data with the path to the original file
            $data[$key] = $originalNewPath;

            // Clean up
            Storage::deleteDirectory("images/tmp/" . $temporaryImage->folder);
            $temporaryImage->delete();
        }

        return $data;
    }

    private function moveFile($folder, $file)
    {
        $tmpPath = "images/tmp/" . $folder . "/" . $file;
        $newPath = "public/images/" . $folder . "/" . $file;
        if (Storage::exists($tmpPath)) {
            Storage::copy($tmpPath, $newPath);
            return str_replace('public/', 'storage/', $newPath);
        }
        return null;
    }

    private function handleOldImage($oldImagePath)
    {
        $oldImage = str_replace('storage/', 'public/', $oldImagePath);
        $oldWebpImage = pathinfo($oldImage, PATHINFO_DIRNAME) . '/' . pathinfo($oldImage, PATHINFO_FILENAME) . '.webp';
    
        // Delete original file
        Storage::delete($oldImage);
    
        // Delete WebP file if it exists
        if (Storage::exists($oldWebpImage)) {
            Storage::delete($oldWebpImage);
        }
    
        $oldFolder = dirname($oldImage);
        if (Storage::allFiles($oldFolder) == []) {
            Storage::deleteDirectory($oldFolder);
        }
    }

    public function responseAcceptance(Form $form)
    {
        $form->update([
            'accept_response' => !$form->accept_response
        ]);
    
        $form->refresh();
    
        return response()->json([
            'is_accepting' => $form->accept_response
        ]);
    }

}
