<?php

namespace App\Exports;

use App\Models\Video;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VideosExport implements FromCollection, WithHeadings
{
    protected $languageId;
    protected $categoryId;
    protected $subCategoryId;
    protected $subjectId;
    protected $topicId;

    public function __construct(
        $languageId = null,
        $categoryId = null,
        $subCategoryId = null,
        $subjectId = null,
        $topicId = null
    ) {
        $this->languageId = $languageId;
        $this->categoryId = $categoryId;
        $this->subCategoryId = $subCategoryId;
        $this->subjectId = $subjectId;
        $this->topicId = $topicId;
    }

    public function collection()
    {
        $query = Video::query();

        if (!is_null($this->topicId)) {
            $query->where('topic_id', $this->topicId);
        }

        if (!is_null($this->subjectId)) {
            $query->whereHas('topic', function ($query) {
                $query->where('subject_id', $this->subjectId);
            });
        }

        if (!is_null($this->subCategoryId)) {
            $query->whereHas('topic.subject', function ($query) {
                $query->where('sub_category_id', $this->subCategoryId);
            });
        }

        if (!is_null($this->categoryId)) {
            $query->whereHas('topic.subject.subCategory', function ($query) {
                $query->where('category_id', $this->categoryId);
            });
        }

        if (!is_null($this->languageId)) {
            $query->whereHas('topic.subject.subCategory.category', function ($query) {
                $query->where('language_id', $this->languageId);
            });
        }



        return $query->get()->map(function ($video) {
            return [
                'id' => $video->id,
                'v_no' => $video->v_no,
                'language_id' => $video->topic->subject->subCategory->category->language->id,
                'category_id' => $video->topic->subject->subCategory->category->id,
                'sub_category_id' => $video->sub_category_id,
                'subject_id' => $video->subject_id,
                'topic_id' => $video->topic_id,
                'name' => $video->name,
                'description' => $video->description,
                'thumbnail' => explode('/', $video->thumbnail)[1] ?? $video->thumbnail,
                'youtube_link' => $video->youtube_link,
                'video_link' =>  is_array($video->video_link)
                    ? collect($video->video_link)->map(function ($path) {

                        preg_match('/(720p|480p|320p)\/([^\/]+)$/', $path, $matches);

                        return isset($matches[1], $matches[2])
                            ? $matches[1] . '/' . $matches[2]
                            : '';
                    })->filter()->implode(', ')
                    : '',
                'video_type' => $video->video_type,
                'video_name' => $video->name . ".mp4",
                'pdf_link' => explode('/', $video->pdf_link)[6] ?? $video->pdf_link,
                'duration' => $video->duration,
            ];
        });
    }

    public function headings(): array
    {
        return [
            'id',
            'v_no',
            'language_id',
            'category_id',
            'sub_category_id',
            'subject_id',
            'topic_id',
            'name',
            'description',
            'thumbnail',
            'youtube_link',
            'video_link',
            'video_type',
            'video_name',
            'pdf_link',
            'duration'
        ];
    }
}
