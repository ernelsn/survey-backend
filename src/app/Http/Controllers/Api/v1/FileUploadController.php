<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Models\TemporaryFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileUploadController extends Controller
{
    public function uploads(Request $request)
    {
        $folder = uniqid(true) . '-' . now()->timestamp;
        $files = [];
    
        $fileTypes = ['image', 'description'];
    
        foreach ($fileTypes as $fileType) {
            if ($request->hasFile($fileType)) {
                $file = $request->file($fileType);
                $fileName = $file->getClientOriginalName();
                $file->storeAs('/images/tmp/' . $folder, $fileName);
    
                $webpFileName = $this->convertToWebp($folder, $fileName, $fileType);

                TemporaryFile::create([
                    'folder' => $folder,
                    'file'  => $fileName,
                    'webp_file' => $webpFileName,
                ]);
    
                $files[] = ['file' => $fileName, 'webp_file' => $webpFileName];
            }
        }
    
        if (!empty($files)) {
            return $folder;
        }
    
        return '';
    }
    
    private function convertToWebp($folder, $fileName, $fileType)
    {
        $webpFileName = pathinfo($fileName, PATHINFO_FILENAME) . '.webp';
        $originalPath = storage_path('app/images/tmp/' . $folder . '/' . $fileName);
        $webpPath = storage_path('app/images/tmp/' . $folder . '/' . $webpFileName);
    
        $cwebpPath = config('app.cwebp_path');
        $quality = config('app.webp_quality', 100);
    
        $command = $this->buildCwebpCommand($cwebpPath, $quality, $originalPath, $webpPath);
    
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
    
        if ($returnCode === 0 && file_exists($webpPath)) {
            return $webpFileName;
        } else {
            // Log::error("WebP conversion failed for {$fileType} file: {$fileName}");
            // Log::error("Command output: " . implode("\n", $output));
            return null;
        }
    }

    public function revert($folder)
    {
        $file = TemporaryFile::where('folder', $folder)->first();
        if($file) {
            Storage::deleteDirectory('/images/tmp/'. $file->folder);
            $file->delete();
        }

        return '';
    }

    private function buildCwebpCommand($cwebpPath, $quality, $inputPath, $outputPath)
    {
        return sprintf('%s -q %d %s -o %s', 
            escapeshellarg($cwebpPath),
            $quality,
            escapeshellarg($inputPath),
            escapeshellarg($outputPath)
        );
    }
}
