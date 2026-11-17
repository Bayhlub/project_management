<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectFileDownloadController extends Controller
{
    /**
     * Download a file attached to one of the user's projects.
     * The route uses scoped bindings, so $file always belongs to $project.
     */
    public function __invoke(Project $project, ProjectFile $file): StreamedResponse
    {
        Gate::authorize('view', $project);

        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name);
    }
}
