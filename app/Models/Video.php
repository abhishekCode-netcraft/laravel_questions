<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Video extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'v_no',
        'thumbnail',
        'topic_id',
        'description',
        'youtube_link',
        'video_link',
        'video_id',
        'duration',
        'video_type',
        'pdf_link',
        'subject_id',
        'sub_category_id',
    ];

    protected $casts = [
        'video_link' => 'array',
    ];

    public function videoProgress()
    {
        return $this->hasOne(VideoProgress::class);
    }

    public function topic()
    {
        return $this->belongsTo(Topic::class);
    }
}
