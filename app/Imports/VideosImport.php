<?php

namespace App\Imports;

use App\Models\Video;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsOnError;
use Maatwebsite\Excel\Concerns\SkipsErrors;
use Maatwebsite\Excel\Concerns\WithValidation;
use Illuminate\Support\Facades\Storage;

class VideosImport implements ToModel, WithHeadingRow, SkipsOnError, WithValidation
{
    use SkipsErrors;

    public function rules(): array
    {
        return [
            'id' => 'nullable',
            'name' => 'required|string|max:255',
            'duration' => 'nullable',
            'v_no' => 'required|max:50',
            'language_id' => 'required|integer',
            'category_id' => 'required|integer',
            'sub_category_id' => 'required|integer',
            'subject_id' => 'required|integer',
            'topic_id' => 'required|integer',
            'description' => 'nullable|string',
            'youtube_link' => 'nullable|string|max:255',
            'video_type' => 'required|string|max:50',
            'video_name' => 'required',
            'pdf_link'    => 'nullable'
        ];
    }

    public function model(array $row)
    {
        $video = Video::find($row['id']);

        if ($video) {
            $videoFileName = $this->createFolderOnServer($row, $video->id);

            $video->update([
                'name' => $row['name'],
                'v_no' => $row['v_no'],
                'duration' => $row['duration'],
                'topic_id' => $row['topic_id'],
                'description' => $row['description'],
                'youtube_link' => $row['youtube_link'],
                'video_type' => $row['video_type'],
                'video_link' => $videoFileName,
                'sub_category_id' => $row['sub_category_id'],
                'subject_id' => $row['subject_id'],
            ]);

            return null;
        } else {
            $video = Video::create([
                'name' => $row['name'],
                'v_no' => $row['v_no'],
                'duration' => $row['duration'],
                'topic_id' => $row['topic_id'],
                'description' => $row['description'],
                'youtube_link' => $row['youtube_link'],
                'video_type' => $row['video_type'],
                //'video_link' => $videoFileName,
                'sub_category_id' => $row['sub_category_id'],
                'subject_id' => $row['subject_id'],
            ]);

            $videoFileName = $this->createFolderOnServer($row, $video->id);
            $video->update([
                'video_link' => $videoFileName
            ]);
        }
    }

    public function createFolderOnServer($row, $id)
    {
        $language  = \App\Models\Language::find($row['language_id']);
        $category  = \App\Models\Category::find($row['category_id']);
        $subCat    = \App\Models\SubCategory::find($row['sub_category_id']);
        $subject   = \App\Models\Subject::find($row['subject_id']);
        $topic     = \App\Models\Topic::find($row['topic_id']);

        $path =
            $language->id . '-' . $language->name . '/' .
            $category->id . '-' . $category->name . '/' .
            $subCat->id . '-' . $subCat->name . '/' .
            $subject->id . '-' . $subject->name . '/' .
            $topic->id . '-' . $topic->name;

        // Create 3 quality folders
        $qualities = ['720p', '480p', '320p'];
        foreach ($qualities as $quality) {
            $fileName = $row['video_name'];
            $fileInfo = pathinfo($fileName);
            $onlyName = explode('.', $fileName)[0];

            $newFileName = $fileInfo['filename'] . '_' . $quality . '.' . $fileInfo['extension'];

            $videoFileName[] = $path . '/videos/' . $id . "-" . $onlyName . '/' . $newFileName;

            Storage::disk('minio')->put($videoFileName[count($videoFileName) - 1], '');
        }

        return $videoFileName;
    }
}
