<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\UserFile;
use App\Services\DoclingService;
use App\Helpers\FileMapper;
use Illuminate\Support\Facades\Storage;

class ProcessGradeOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userFileId
    ) {}

    public function handle(DoclingService $doclingService): void
    {
        $userFile = UserFile::find($this->userFileId);
        
        if (!$userFile) {
            return;
        }

        $disk = FileMapper::resolveDiskForPath($userFile->file_path);
        $fileData = Storage::disk($disk)->get($userFile->file_path);

        if (!$fileData) {
            return;
        }

        $doclingJson = $doclingService->convertToJson($fileData, basename($userFile->file_path));

        if ($doclingJson) {
            $userFile->update([
                'docling_json' => $doclingJson,
            ]);
        }
    }
}
