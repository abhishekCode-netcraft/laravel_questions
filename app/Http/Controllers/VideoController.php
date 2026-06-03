<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Language;
use App\Models\Course;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use App\Exports\VideosExport;
use App\Exports\SampleVideosExport;
use App\Imports\VideosImport;
use App\Models\Topic;
use App\Models\Subject;
use App\Models\Video;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage; // ✅ Correctly placed here
use Illuminate\Support\Facades\Validator;
use App\Models\VideoProgress;
use FFMpeg\FFProbe;


class VideoController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $languages = Language::all();
        $categories = Category::all();
        $subcategories = SubCategory::all();
        $subjects = Subject::all();
        $topics = Topic::all();

        $subject_id = $request->get('subject_id');
        $subcategory_id = $request->get('sub_category_id');
        $category_id = $request->get('category_id');
        $language_id = $request->get('language_id');
        $topic_id = $request->get('topic_id');

        $sortColumn = $request->get('sort', 'videos.id');
        $sortDirection = $request->get('direction', 'desc');

        // Prepare the query
        $query = Video::select('videos.*')->with([
            'topic' => function ($q) {
                $q->with(['subject' => function ($q) {
                    $q->with(['subCategory' => function ($q) {
                        $q->with('category.language');
                    }]);
                }]);
            }
        ]);

        if ($topic_id) {
            $query->where('topic_id', $topic_id);
        }
        if ($subject_id) {
            $query->whereHas('topic.subject', function ($q) use ($subject_id) {
                $q->where('id', $subject_id);
            });
        }
        if ($subcategory_id) {
            $query->whereHas('topic.subject.subCategory', function ($q) use ($subcategory_id) {
                $q->where('id', $subcategory_id);
            });
        }
        if ($category_id) {
            $query->whereHas('topic.subject.subCategory.category', function ($q) use ($category_id) {
                $q->where('id', $category_id);
            });
        }
        if ($language_id) {
            $query->whereHas('topic.subject.subCategory.category.language', function ($q) use ($language_id) {
                $q->where('id', $language_id);
            });
        }

        // Validate sort column
        $sortableColumns = [
            'id' => 'videos.id',
            'name' => 'videos.name',
            'language' => 'languages.name',
            'category' => 'categories.name',
            'sub_category' => 'sub_categories.name',
            'subject' => 'subjects.name',
            'topic' => 'topics.name',
        ];

        if (array_key_exists($sortColumn, $sortableColumns)) {
            // Include necessary joins for sorting by related columns
            $query->join('topics', 'videos.topic_id', '=', 'topics.id')
                ->join('subjects', 'topics.subject_id', '=', 'subjects.id')
                ->join('sub_categories', 'subjects.sub_category_id', '=', 'sub_categories.id')
                ->join('categories', 'sub_categories.category_id', '=', 'categories.id')
                ->join('languages', 'categories.language_id', '=', 'languages.id');

            // Apply sorting using the proper table aliases
            $query->orderBy($sortableColumns[$sortColumn], $sortDirection);
        }

        if (request()->data == 'all') {
            $videos = $query
                ->orderBy('desc')
                ->get();
        } else {
            $videos = $query
                ->orderBy('id', 'desc')
                ->paginate(request()->data);
        }

        $dropdown_list = [
            'Select Language' => $languages,
            'Select Category' => $categories,
            'Select Sub Category' => $subcategories ?? [],
            'Select Subject' => $subjects ?? [],
            'Select Topic' => $topics ?? [],
        ];

        // Return the view with all necessary data
        return view('videos.index', compact(
            'videos',
            'categories',
            'subcategories',
            'subjects',
            'languages',
            'topics',
            'subject_id',
            'subcategory_id',
            'category_id',
            'language_id',
            'topic_id',
            'sortColumn',
            'sortDirection',
            'dropdown_list'
        ));
    }

    public function export(Request $request)
    {
        $languageId = $request->get('language_id');
        $categoryId = $request->get('category_id');
        $subCategoryId = $request->get('sub_category_id');
        $subjectId = $request->get('subject_id');
        $topicId = $request->get('topic_id');

        return Excel::download(new VideosExport($languageId, $categoryId, $subCategoryId,  $subjectId, $topicId), 'videos.xlsx');
    }

    public function sample(Request $request)
    {
        return Excel::download(new SampleVideosExport(), 'sample-videos.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new VideosImport();

        try {
            $rows = Excel::import($importer, $request->file('file'));

            foreach ($rows as $key => $row) {
                $rowCount = $key + 2;

                if (empty($row['topic_id'])) {
                    return back()->with('error', 'Row: ' . $rowCount . '- topic is required.');
                }

                if (!$this->getTopicId($row['topic_id'])) {
                    return back()->with('error', 'topic - "' . $row['topic_id'] . '" not available');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('videos.index')
                ->with('import_errors', [$e->getMessage()]);
        }

        if (!empty($importer->errors)) {
            return redirect()->route('videos.index')
                ->with('import_errors', $importer->errors);
        }

        return redirect()->route('videos.index')->with('success', 'videos imported successfully!');
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //      dd(request()->all());
        $request->validate([
            'name' => 'required',
            'sub_category_id' => 'required',
            'subject_id' => 'required',
            'topic_id' => 'required',
            'video' => 'required|file|max:204800', // example validation
        ]);

        $video = Video::create(array_merge($request->except(['thumbnail', 'pdf_link', 'video']), [
            'duration' => now()->format('H:i:s')
        ]));

        // Upload to MinIO
        if ($request->hasFile('thumbnail')) {
            $fileName = "thumbnail/" . time() . "_photo.jpg";
            $request->file('thumbnail')->storePubliclyAs('public', $fileName);
            $video->thumbnail = $fileName;
        }

        if ($request->hasFile('pdf_link')) {
            $path = request()->language_id . "/" . request()->category_id . "/" . request()->sub_category_id . "/" . request()->subject_id . "/" . request()->topic_id;
            $pdfFileName = $path . '/pdfs/' . $request->file('pdf_link')->getClientOriginalName();
            Storage::disk('minio')->put($pdfFileName, '');
            $video->pdf_link = $pdfFileName;
        }

        if ($request->hasFile('video')) {
            $path = request()->language_id . "/" . request()->category_id . "/" . request()->sub_category_id . "/" . request()->subject_id . "/" . request()->topic_id;
            $videoFileName = $path . '/videos/' . $request->file('video')->getClientOriginalName();
            Storage::disk('minio')->put($videoFileName, '');
            $video->video_link = $videoFileName;
        }

        $video->save();

        return response()->json([
            'success' => true,
            'message' => 'Video Successfully Created',
            'video' => $video
        ]);
    }

    public function listVideos()
    {
        $files = Storage::disk('s3')->files('videos');
        $urls = array_map(fn($file) => Storage::disk('s3')->url($file), $files);

        return response()->json($urls);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */

    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'sub_category_id' => 'required',
            'subject_id' => 'required',
            'topic_id' => 'required'
        ]);

        // $video = Video::find($id);
        if (!$video = Video::find($id)) {
            return response()->json(['success' => false, 'message' => 'Video not found'], 404);
        }

        // Update text fields
        $video->update($request->except(['thumbnail', 'pdf_link', 'video']));

        // === Handle Thumbnail ===
        if ($request->hasFile('thumbnail')) {
            if ($video->thumbnail && Storage::disk('minio')->exists($video->thumbnail)) {
                Storage::disk('minio')->delete($video->thumbnail);
            }

            $fileName = "thumbnail/" . time() . "_photo.jpg";
            $request->file('thumbnail')->storePubliclyAs('public', $fileName);
            $video->thumbnail = $fileName;
        }

        // === Handle PDF ===
        if ($request->hasFile('pdf_link')) {
            if ($video->pdf_link && Storage::disk('minio')->exists($video->pdf_link)) {
                Storage::disk('minio')->delete($video->pdf_link);
            }

            $path = request()->language_id . "/" . request()->category_id . "/" . request()->sub_category_id . "/" . request()->subject_id . "/" . request()->topic_id;
            $pdfFileName = $path . 'pdfs/' . $request->file('pdf_link')->getClientOriginalName();
            Storage::disk('minio')->put($pdfFileName, '');
            $video->pdf_link = $pdfFileName;
        }

        // === Handle Video ===
        if ($request->hasFile('video')) {
            if ($video->video && Storage::disk('minio')->exists($video->video)) {
                Storage::disk('minio')->delete($video->video);
            }

            $path = request()->language_id . "/" . request()->category_id . "/" . request()->sub_category_id . "/" . request()->subject_id . "/" . request()->topic_id;
            $videoFileName = $path . '/videos/' . $request->file('video')->getClientOriginalName();

            // Create an empty .mp4 file
            Storage::disk('minio')->put($videoFileName, '');

            // Save the path in database
            $video->video_link = $videoFileName;
        }

        $video->save();

        return response()->json([
            'success' => true,
            'message' => 'Video Successfully Updated',
            'video' => $video
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Video::destroy($id);

        session()->flash('success', 'Video Successfully Deleted');

        return redirect()->route('videos.index');
    }

    public function formattedAPI(Request $request, $userId, $courseId)
    {
        $validator = Validator::make($request->all(), [
            'SubCategory' => 'required',
            'Subject' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $requestData = $request->all();

        if (!$course = Course::find($courseId)) return response()->json(['error' => 'Course not found'], 404);

        if ($course->language) {
            $categoryId = $course->category_id;
            $category = Category::find($categoryId);
            $subcategory = SubCategory::find($requestData['SubCategory']);
            $subject = Subject::find($requestData['Subject']);

            $requestData['Language_2'] = $course->language_id;
            $requestData['Category_2'] = $categoryId;
            $requestData['SubCategory_2'] = $requestData['SubCategory'];
            $requestData['Subject_2'] = $requestData['Subject'];

            $requestData['Language'] = 1;
            $requestData['Category'] = $category->parent_id;
            $requestData['SubCategory'] = $subcategory->parent_id ? $subcategory->parent_id : $subcategory->id;
            $requestData['Subject'] = $subject->parent_id;
        } else {
            $requestData['Language'] = $course->language_id;
            $requestData['Category'] = $course->category_id;
        }

        $data = $this->getFirstDropdownData($requestData);
        $language = $data['language'] ?? null;
        $categories = $data['categories'][0];
        $subcategories = $data['subcategories'][0];
        $subjects = $data['subjects'];
        $topics = $data['topics'];

        $data2 = $this->getSecondDropdownData($requestData);
        $language2 = $data2['language'] ?? null;
        $categories2 = $data2['categories'] ?? [];
        $subcategories2 = $data2['subcategories'] ?? [];
        $subjects2 = $data2['subjects'] ?? [];
        $topics2 = $data2['topics'] ?? [];

        $jsonResponse = [];
        $languageName = '<span class="notranslate">' . $language->name . '</span>';
        if ($language2) {
            $languageName .= ' | ' . $language2->name;
        }

        $categoryName = '<span class="notranslate">' . $categories->name . '</span>';
        if (count($categories2)) {
            $categoryName .= ' | ' . $categories2[0]->name;
        }

        $subcategoryName = '<span class="notranslate">' . $subcategories->name . '</span>';
        if (count($subcategories2)) {
            $subcategoryName .= ' | ' . $subcategories2[0]->name;
        }

        $subjectName = '<span class="notranslate">' . $subjects->name . '</span>';
        if (count($subjects2)) {
            $subjectName .= ' | ' . $subjects2[0]->name;
        }

        foreach ($topics as $outkey => $topic) {
            $topicsName = '<span class="notranslate">' . $topic->name . "</span>";
            if (count($topics2)) {
                $topicsName .= ' | ' . ($topics2[$outkey]->name ?? '');
            }

            if (isset($topics2[$outkey])) {
                if (in_array($topics2[$outkey]->id, $course->topic_id)) {
                    $videos = $this->getFirstDropdownData($requestData, $topics2[$outkey])
                        ? $this->getFirstDropdownData($requestData, $topics2[$outkey])['videos']
                        : [];

                    $videos->map(function ($video) {
                        if ($video->videoProgress) {
                            $video->duration = $video->videoProgress->video_total_seconds;
                        }

                        unset($video['videoProgress']);
                    });

                    $jsonResponse[$languageName][$categoryName][$subcategoryName][$subjectName][$topics[$outkey]->id][$topicsName] = $videos;
                }
            }
        }

        return response()->json($jsonResponse);
    }

    public function getQuestionsData($language_id, $category_id, $subcategory_id)
    {
        $subjects1 = Subject::where('sub_category_id', $subcategory_id)
            ->get();

        $subjects2 = [];

        foreach ($subjects1 as $subject) {
            $questions = Question::where('subject_id', $subject->id)
                ->where('language_id', $language_id)
                ->where('category_id',  $category_id)
                ->get()
                ->toArray();

            $questions = count($questions);

            $subject->questions = $questions;

            $subjects2[] = Subject::where('parent_id', $subject->id)->first()?->toArray() ?? [];
        }

        return response()->json(['subjects1' => $subjects1, 'subjects2' => $subjects2]);
    }

    function getFirstDropdownData($data, $topic = null)
    {
        $languageId = $data['Language'] ?? null;
        $categoryId = $data['Category'] ?? null;
        $subjectId = $data['Subject'] ?? null;
        $subCategory2 = $data['SubCategory_2'] ?? null;
        $subject2 = $data['Subject_2'] ?? null;

        if (!$categoryId) return response()->json(['error' => 'Category parameter is missing'], 400);

        $videos = Video::with('videoProgress')
            ->where('sub_category_id', $subCategory2)
            ->where('subject_id', $subject2)
            ->where('topic_id', $topic?->id);

        $videos = $videos->get();

        $videos->map(function ($video) {
            if ($video->video_link) {
                $paths = is_array($video->video_link)
                    ? $video->video_link
                    : json_decode($video->video_link, true);

                $videoUrls = [];

                if (is_array($paths)) {
                    foreach ($paths as $path) {
                        try {
                            $quality = '';
                            if (str_contains($path, '_720p')) {
                                $quality = '720p';
                            } elseif (str_contains($path, '_480p')) {
                                $quality = '480p';
                            } elseif (str_contains($path, '_320p')) {
                                $quality = '320p';
                            }

                            // $ffprobe = FFProbe::create();
                            // $tempUrl = Storage::disk('s3')->temporaryUrl(
                            // $path,
                            // now()->addMinutes(30)
                            // );

                            //$duration = $ffprobe
                            //->format($tempUrl) // local file path
                            //->get('duration');

                            $videoUrls[] = [
                                'quality' => $quality,
                                'url' => Storage::disk('s3')->temporaryUrl(
                                    $path,
                                    now()->addMinutes(30)
                                ),
                                //'duration' => $duration
                            ];
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }

                $video->videoURL = $videoUrls;
            } else {
                $video->videoURL = [];
            }

            if ($video->pdf_link) {
                $video->pdfURL = Storage::disk('s3')->temporaryUrl(
                    $video->pdf_link,
                    now()->addMinutes(30)
                );
            } else {
                $video->pdfURL = null;
            }

            return $video;
        });

        $language = Language::find($languageId);
        $categories = isset($categoryId) ? Category::where('id', $categoryId)->get() : Category::where('language_id', $languageId)->get();
        $subcategories = isset($data['SubCategory']) ? SubCategory::where('id', $data['SubCategory'])->get() : SubCategory::where('category_id', $categoryId)->get();
        $subjects = Subject::find($subjectId);
        $topics = Topic::where('subject_id', $subjects->id)->get();

        return [
            'language' => $language,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subjects' => $subjects,
            'topics' => $topics,
            'videos' => $videos
        ];
    }

    function getSecondDropdownData($data)
    {
        $languageId = $data['Language_2'] ?? null;

        $categoryId = $data['Category_2'] ?? null;

        if (!$categoryId) return null;

        $videos = Video::with('videoProgress')
            ->where('sub_category_id', $data['SubCategory_2'])
            ->where('subject_id', $data['Subject_2'])
            ->get();

        $language = Language::find($languageId);
        $categories = isset($categoryId) ? Category::where('id', $categoryId)->get() : Category::where('language_id', $languageId)->get();
        $subcategories = isset($data['SubCategory_2']) ? SubCategory::where('id', $data['SubCategory_2'])->get() : SubCategory::where('category_id', $categoryId)->get();
        $subjects = Subject::whereIn('sub_category_id', $subcategories->pluck('id')->toArray())->get();
        $topics = isset($data['Topic_2']) ? Topic::where('id', $data['Topic_2'])->get() : Topic::whereIn('subject_id', $subjects->pluck('id')->toArray())->get();

        return [
            'language' => $language,
            'categories' => $categories,
            'subcategories' => $subcategories,
            'subjects' => $subjects,
            'topics' => $topics,
            'videos' => $videos
        ];
    }

    public function updateVideoProgress(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:google_users,id',
            'video_id' => 'required|exists:videos,id',
            'watched_seconds' => 'required|numeric|min:0',
            'video_total_seconds' => 'required|numeric|min:0',
        ]);

        $videoProgress = VideoProgress::updateOrCreate(
            ['user_id' => $validated['user_id'], 'video_id' => $validated['video_id']],
            ['watched_seconds' => $validated['watched_seconds'], 'video_total_seconds' => $validated['video_total_seconds']]
        );

        return response()->json(['message' => 'Video progress updated successfully', 'data' => $videoProgress]);
    }

    public function getVideoProgress($userId, $sub_category_id)
    {
        $videoProgressData = VideoProgress::join('videos', 'video_progress.video_id', '=', 'videos.id')
            ->where('video_progress.user_id', $userId)
            ->where('videos.sub_category_id', $sub_category_id)
            ->select(
                'videos.id',
                'videos.name',
                'videos.v_no',
                'videos.topic_id',
                'videos.duration',
                'videos.created_at',
                'videos.updated_at',
                'video_progress.watched_seconds',
                'video_total_seconds'
            )
            ->get();

        $totalVideos = Video::where('sub_category_id', $sub_category_id)
            ->get();

        // Total videos
        $totalVideos = $totalVideos->count();

        // Videos considered "watched" (>= 90% watch)
        $watchedVideos = $videoProgressData->filter(function ($item) {
            return $item->watched_seconds >= (0.9 * $item->video_total_seconds);
        })->count();

        // Sum of all video durations
        $totalWatchTime = $videoProgressData->sum('video_total_seconds');

        // Sum of watched seconds for videos >= 90% watched
        $completedWatchTime = $videoProgressData->filter(function ($item) {
            return $item->watched_seconds >= (0.9 * $item->video_total_seconds);
        })->sum('watched_seconds');

        return response()->json([
            'data' => $videoProgressData,
            'total_videos' => $totalVideos,
            'watched_videos' => $watchedVideos,
            'total_video_time' => $totalWatchTime,
            'completed_watch_time' => $completedWatchTime,
        ]);
    }
}
