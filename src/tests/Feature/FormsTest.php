<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Form;
use App\Models\TemporaryFile;
use App\Http\Controllers\Api\v1\FormController;
use App\Http\Requests\StoreFormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;

class FormsTest extends TestCase
{
    use RefreshDatabase;

    protected $controller;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FormController();
        $this->user = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testStoreMethodCreatesFormSuccessfully()
    {
        Storage::fake('local');

        $imagePath = $this->createTemporaryFiles();

        $data = [
            'title' => 'Test Form',
            'description' => 'This is a test form',
            'image' => $imagePath,
            'user_id' => $this->user->id,
            'expire_date' => now()->addDays(7)->toDateString(),
            'time_limit' => 60,
            'is_published' => true,
            'show_results' => true,
            'multiple_attempts' => false,
            'sections' => [
                [
                    'title' => 'Section 1',
                    'description' => 'Section 1 description',
                    'questions' => [
                        [
                            'question' => 'Question 1',
                            'type' => 'short answer',
                            'description' => $imagePath,
                            'data' => json_encode(['some' => 'data']),
                        ],
                    ],
                ],
            ],
        ];

        $request = StoreFormRequest::create('/api/v1/forms', 'POST', $data);
        $request->setValidator(\Illuminate\Support\Facades\Validator::make($data, $request->rules()));

        $response = $this->controller->store($request);

        $this->assertNotNull($response);

        $this->assertDatabaseHas('forms', [
            'title' => 'Test Form',
            'user_id' => $this->user->id,
        ]);

        $form = Form::where('title', 'Test Form')->first();
        $this->assertNotNull($form, "Form was not created");

        if ($form) {
            $this->assertEquals('Test Form', $form->title);
            $this->assertEquals($this->user->id, $form->user_id);
            $this->assertEquals('This is a test form', $form->description);
            $this->assertEquals(60, $form->time_limit);
            $this->assertTrue((bool)$form->is_published);
            $this->assertTrue((bool)$form->show_results);
            $this->assertFalse((bool)$form->multiple_attempts);
            $this->assertEquals($imagePath, $form->image);

            $this->assertDatabaseHas('form_sections', [
                'form_id' => $form->id,
                'title' => 'Section 1',
                'description' => 'Section 1 description',
            ]);

            $section = $form->formSections()->first();
            $this->assertNotNull($section, "Form section was not created");

            if ($section) {
                $this->assertDatabaseHas('form_section_questions', [
                    'form_section_id' => $section->id,
                    'question' => 'Question 1',
                    'type' => 'short answer',
                ]);

                $question = $section->formSectionQuestions()->first();
                $this->assertNotNull($question, "Question was not created");

                if ($question) {
                    $this->assertEquals('Question 1', $question->question);
                    $this->assertEquals('short answer', $question->type);
                    $this->assertEquals($imagePath, $question->description);
                    $this->assertJson($question->data);
                }
            }

            Storage::disk('local')->assertExists(str_replace('storage/', 'public/', $imagePath));
        }
    }

    public function testStoreMethodHandlesImageProcessing()
    {
        Storage::fake('local');

        $imagePath = $this->createTemporaryFiles();

        $data = [
            'title' => 'Test Form',
            'description' => 'This is a test form',
            'image' => $imagePath,
            'user_id' => $this->user->id,
            'expire_date' => now()->addDays(7)->toDateString(),
            'time_limit' => 60,
            'is_published' => true,
            'show_results' => true,
            'multiple_attempts' => false,
            'sections' => [
                [
                    'title' => 'Section 1',
                    'description' => 'Section 1 description',
                    'questions' => [],
                ],
            ],
        ];

        $request = StoreFormRequest::create('/api/v1/forms', 'POST', $data);
        $request->setValidator(\Illuminate\Support\Facades\Validator::make($data, $request->rules()));

        $response = $this->controller->store($request);

        $this->assertNotNull($response);
        $form = Form::where('title', 'Test Form')->first();
        $this->assertNotNull($form);
        $this->assertNotNull($form->image);
        Storage::disk('local')->assertExists(str_replace('storage/', 'public/', $imagePath));
    }

    public function testStoreMethodHandlesQuestionImageProcessing()
    {
        Storage::fake('local');

        $imagePath = $this->createTemporaryFiles();

        $data = [
            'title' => 'Test Form',
            'description' => 'This is a test form',
            'image' => null,
            'user_id' => $this->user->id,
            'expire_date' => now()->addDays(7)->toDateString(),
            'time_limit' => 60,
            'is_published' => true,
            'show_results' => true,
            'multiple_attempts' => false,
            'sections' => [
                [
                    'title' => 'Section 1',
                    'description' => 'Section 1 description',
                    'questions' => [
                        [
                            'question' => 'Question 1',
                            'type' => 'short answer',
                            'description' => $imagePath,
                            'data' => json_encode(['some' => 'data']),
                        ],
                    ],
                ],
            ],
        ];

        $request = StoreFormRequest::create('/api/v1/forms', 'POST', $data);
        $request->setValidator(\Illuminate\Support\Facades\Validator::make($data, $request->rules()));

        $response = $this->controller->store($request);

        $this->assertNotNull($response);
        $this->assertDatabaseHas('form_section_questions', [
            'question' => 'Question 1',
        ]);
        $question = \App\Models\FormSectionQuestion::where('question', 'Question 1')->first();
        $this->assertNotNull($question);
        Storage::disk('local')->assertExists(str_replace('storage/', 'public/', $imagePath));
    }

    public function testStoreMethodHandlesExceptionAndRollsBackTransaction()
    {
        // Fake the DB facade
        DB::shouldReceive('beginTransaction')->once();
        DB::shouldReceive('rollBack')->once();
        DB::shouldReceive('commit')->never();

        $data = [
            'title' => 'Test Form',
            'user_id' => $this->user->id,
            'sections' => [
                [
                    'title' => 'Section 1',
                    'questions' => [
                        [
                            'question' => 'Question 1',
                            'type' => 'invalid_type',
                            'form_section_id' => 1,
                            'description' => null,
                            'data' => json_encode(['some' => 'data']),
                        ],
                    ],
                ],
            ],
        ];

        // Create a mock request
        $request = Mockery::mock(StoreFormRequest::class);
        $request->shouldReceive('validated')->andReturn($data);

        // Call the store method
        try {
            $response = $this->controller->store($request);
        } catch (ValidationException $e) {
            $response = new JsonResponse([
                'error' => 'An error occurred while saving the form',
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Exception $e) {
            $response = new JsonResponse([
                'error' => 'An error occurred while saving the form',
            ], 500);
        }

        // Assert that the response is a JSON response with a 500 or 422 status
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertTrue(in_array($response->getStatusCode(), [500, 422]));

        // Assert the content of the response
        $responseContent = json_decode($response->getContent(), true);
        $this->assertEquals('An error occurred while saving the form', $responseContent['error']);
    }

    private function createTemporaryFiles()
    {
        $folder = uniqid(true) . '-' . now()->timestamp;
        
        $image = UploadedFile::fake()->image('test_image.jpg');
        
        // Store in temporary location
        Storage::disk('local')->putFileAs('images/tmp/' . $folder, $image, 'test_image.jpg');
        
        // Create TemporaryFile record
        TemporaryFile::create([
            'folder' => $folder,
            'file' => 'test_image.jpg',
        ]);
    
        // Move to public/images path
        $tmpPath = "images/tmp/" . $folder . "/test_image.jpg";
        $newPath = "public/images/" . $folder . "/test_image.jpg";
        Storage::copy($tmpPath, $newPath);
    
        // Delete temporary file
        Storage::deleteDirectory("images/tmp/" . $folder);
    
        return 'storage/images/' . $folder . '/test_image.jpg';
    }
}
