<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Language;
use App\Models\LiveTest;
use App\Models\Question;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\LiveTestManualQuestion;
use App\Exports\LiveTestManualTemplateExport;
use App\Imports\LiveTestManualImport;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LiveTestController extends Controller
{
    public function index()
    {
        $languages = Language::all();
        $categories = Category::all();
        $liveTests = LiveTest::with(['language', 'category'])->latest()->paginate(10);

        return view('live_tests.index', compact('languages', 'categories', 'liveTests'));
    }

    public function getQuestions(Request $request)
    {
        $languageId = $request->language_id;
        $categoryId = $request->category_id;
        $subCategoryId = $request->sub_category_id; // Changed from sub_category_ids to sub_category_id

        if (!$languageId || !$categoryId || !$subCategoryId) {
            return response()->json(['subjects' => [], 'subject_count' => 0, 'question_count' => 0]);
        }

        $subCategoryIds = (array) $subCategoryId; // Convert to array for matching logic

        // Get matching course to find question limit
        $allCourses = Course::where('language_id', $languageId)
            ->where('category_id', $categoryId)
            ->get();

        $matchingCourses = $allCourses->filter(function ($c) use ($subCategoryIds) {
            $courseSubs = array_map('strval', (array) $c->sub_category_id);
            $inputSubs = array_map('strval', (array) $subCategoryIds);
            return count(array_intersect($courseSubs, $inputSubs)) > 0;
        });

        $subjectsWithQuestions = [];
        $totalQuestionsCount = 0;
        $maxGlobalLimit = 0;

        $selectedSubIds = array_map('strval', (array) $subCategoryId);

        // 1. Aggregate all Subject IDs and their highest limits from matching courses
        $sidLimitMap = [];
        foreach ($matchingCourses as $course) {
            $courseSids = (array) ($course->subject_id ?? []);
            if ($course->subject_limit && is_array($course->subject_limit)) {
                $courseSids = array_unique(array_merge($courseSids, array_keys($course->subject_limit)));
            }

            foreach ($courseSids as $sid) {
                if ($sid === null || $sid === '')
                    continue;
                $sidStr = strval($sid);

                // Determine limit for this subject in this course
                $limit = (int) ($course->subject_limit[$sid] ?? $course->question_limit);
                if (!isset($sidLimitMap[$sidStr]) || $limit > $sidLimitMap[$sidStr]) {
                    $sidLimitMap[$sidStr] = $limit;
                }
            }

            if ((int) $course->question_limit > $maxGlobalLimit) {
                $maxGlobalLimit = (int) $course->question_limit;
            }
        }

        $uniqueSubjectIds = array_keys($sidLimitMap);

        // 2. Process each unique subject
        foreach ($uniqueSubjectIds as $sid) {
            $subject = Subject::find($sid);
            if (!$subject)
                continue;

            // CRITICAL: Filter by the selected subcategory only (prevents mixed-year issues)
            if (!in_array(strval($subject->sub_category_id), $selectedSubIds)) {
                continue;
            }

            // Get questions for this subject and language
            $questions = Question::where('subject_id', $sid)
                ->where('language_id', $languageId)
                ->get();

            if ($questions->count() > 0) {
                $limit = $sidLimitMap[$sid] ?? $maxGlobalLimit ?: 50;
                if ($limit <= 0)
                    $limit = 50;

                $subjectsWithQuestions[] = [
                    'id' => $subject->id,
                    'name' => $subject->name,
                    'questions' => $questions,
                    'limit' => $limit
                ];
                $totalQuestionsCount += $questions->count();
            }
        }

        return response()->json([
            'subjects' => $subjectsWithQuestions,
            'subject_count' => count($subjectsWithQuestions),
            'question_count' => $totalQuestionsCount,
            'limit' => $maxGlobalLimit ?: "NO LIMIT"
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'language_id' => 'required|exists:languages,id',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required', // Changed from sub_category_ids to sub_category_id
            'mode' => 'required|in:auto,manual',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'toppers_star' => 'nullable|integer',
            'toppers' => 'nullable|integer',
            'participant_star' => 'nullable|integer',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'question_ids' => 'required_if:mode,auto|array',
        ]);

        $photoPath = null;
        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('live_tests', 'public');
        }

        $liveTest = LiveTest::create([
            'language_id' => $request->language_id,
            'category_id' => $request->category_id,
            'sub_category_id' => (array) $request->sub_category_id,
            'mode' => $request->mode,
            'title' => $request->title,
            'photo' => $photoPath,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'toppers_star' => $request->toppers_star,
            'toppers' => $request->toppers,
            'participant_star' => $request->participant_star,
            'question_ids' => $request->mode == 'auto' ? $request->question_ids : null,
            'status' => true,
        ]);

        if ($request->mode == 'manual' && $request->has('manual_questions')) {
            $questions = json_decode($request->manual_questions, true);
            if (is_array($questions)) {
                foreach ($questions as $q) {
                    $liveTest->manualQuestions()->create([
                        'language_id' => $request->language_id,
                        'category_id' => $q['category'] ?? ($q['category_id'] ?? null),
                        'sub_category_id' => $q['subcategory'] ?? ($q['sub_category_id'] ?? null),
                        'subject_id' => $q['subject'] ?? ($q['subject_id'] ?? null),
                        'question' => $q['question'] ?? null,
                        'option_a' => $q['option_a'] ?? null,
                        'option_b' => $q['option_b'] ?? null,
                        'option_c' => $q['option_c'] ?? null,
                        'option_d' => $q['option_d'] ?? null,
                        'answer' => $q['answer'] ?? null,
                        'photo' => $q['photo'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Live Test created successfully']);
    }

    public function destroy($id)
    {
        LiveTest::destroy($id);
        return redirect()->route('live-tests.index')->with('success', 'Live Test deleted successfully');
    }

    public function edit($id)
    {
        $liveTest = LiveTest::with('manualQuestions')->findOrFail($id);
        $liveTest->photo_url = $liveTest->photo ? asset('storage/' . $liveTest->photo) : null;
        return response()->json($liveTest);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'language_id' => 'required|exists:languages,id',
            'category_id' => 'required|exists:categories,id',
            'sub_category_id' => 'required',
            'mode' => 'required|in:auto,manual',
            'title' => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'toppers_star' => 'nullable|integer',
            'toppers' => 'nullable|integer',
            'participant_star' => 'nullable|integer',
            'photo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'question_ids' => 'required_if:mode,auto|array',
        ]);

        $liveTest = LiveTest::findOrFail($id);

        $photoPath = $liveTest->photo;
        if ($request->hasFile('photo')) {
            if ($photoPath) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($photoPath);
            }
            $photoPath = $request->file('photo')->store('live_tests', 'public');
        }

        $liveTest->update([
            'language_id' => $request->language_id,
            'category_id' => $request->category_id,
            'sub_category_id' => (array) $request->sub_category_id,
            'mode' => $request->mode,
            'title' => $request->title,
            'photo' => $photoPath,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'toppers_star' => $request->toppers_star,
            'toppers' => $request->toppers,
            'participant_star' => $request->participant_star,
            'question_ids' => $request->mode == 'auto' ? $request->question_ids : null,
        ]);

        if ($request->mode == 'manual' && $request->has('manual_questions')) {
            $liveTest->manualQuestions()->delete();
            $questions = json_decode($request->manual_questions, true);
            if (is_array($questions)) {
                foreach ($questions as $q) {
                    $liveTest->manualQuestions()->create([
                        'language_id' => $request->language_id,
                        'category_id' => $q['category'] ?? ($q['category_id'] ?? null),
                        'sub_category_id' => $q['subcategory'] ?? ($q['sub_category_id'] ?? null),
                        'subject_id' => $q['subject'] ?? ($q['subject_id'] ?? null),
                        'question' => $q['question'] ?? null,
                        'option_a' => $q['option_a'] ?? null,
                        'option_b' => $q['option_b'] ?? null,
                        'option_c' => $q['option_c'] ?? null,
                        'option_d' => $q['option_d'] ?? null,
                        'answer' => $q['answer'] ?? null,
                        'photo' => $q['photo'] ?? null,
                    ]);
                }
            }
        }

        return response()->json(['success' => true, 'message' => 'Live Test updated successfully']);
    }

    public function downloadManualTemplate(Request $request)
    {
        $languageId = $request->language_id;
        $categoryId = $request->category_id;
        $subCategoryId = $request->sub_category_id;

        $matchingCourses = Course::where('language_id', $languageId)
            ->where('category_id', $categoryId)
            ->get()
            ->filter(function ($c) use ($subCategoryId) {
                $courseSubs = array_map('strval', (array) $c->sub_category_id);
                return in_array(strval($subCategoryId), $courseSubs);
            });

        $templateData = [];
        $selectedSubIdStr = strval($subCategoryId);

        $sidLimitMap = [];
        $maxGlobalLimit = 0;

        foreach ($matchingCourses as $course) {
            $courseSids = (array) ($course->subject_id ?? []);
            if ($course->subject_limit && is_array($course->subject_limit)) {
                $courseSids = array_unique(array_merge($courseSids, array_keys($course->subject_limit)));
            }

            foreach ($courseSids as $sid) {
                if ($sid === null || $sid === '')
                    continue;
                $sidStr = strval($sid);
                $limit = (int) ($course->subject_limit[$sid] ?? $course->question_limit);
                if (!isset($sidLimitMap[$sidStr]) || $limit > $sidLimitMap[$sidStr]) {
                    $sidLimitMap[$sidStr] = $limit;
                }
            }

            if ((int) $course->question_limit > $maxGlobalLimit) {
                $maxGlobalLimit = (int) $course->question_limit;
            }
        }

        $uniqueSubjectIds = array_keys($sidLimitMap);

        foreach ($uniqueSubjectIds as $sid) {
            $subject = Subject::find($sid);
            if (!$subject || strval($subject->sub_category_id) !== $selectedSubIdStr)
                continue;

            $limit = $sidLimitMap[$sid] ?? $maxGlobalLimit ?: 50;
            if ($limit <= 0)
                $limit = 50;

            for ($i = 0; $i < $limit; $i++) {
                $templateData[] = [
                    'language_id' => $languageId,
                    'category' => $categoryId,
                    'subcategory' => $subCategoryId,
                    'subject' => $sid,
                    'question' => '',
                    'option_a' => '',
                    'option_b' => '',
                    'option_c' => '',
                    'option_d' => '',
                    'answer' => '',
                    'photo' => ''
                ];
            }
        }

        if (empty($templateData)) {
            $templateData[] = [
                'language_id' => $languageId,
                'category' => $categoryId,
                'subcategory' => $subCategoryId,
                'subject' => '',
                'question' => '',
                'option_a' => '',
                'option_b' => '',
                'option_c' => '',
                'option_d' => '',
                'answer' => '',
                'photo' => ''
            ];
        }

        return Excel::download(new LiveTestManualTemplateExport($templateData), 'live_test_manual_template.xlsx');
    }

    public function previewManualData(Request $request)
    {
        if (!$request->hasFile('excel_file')) {
            return response()->json(['success' => false, 'message' => 'No file uploaded']);
        }

        try {
            $rows = Excel::toCollection(new LiveTestManualImport, $request->file('excel_file'))->first();
            return response()->json([
                'success' => true,
                'data' => $rows
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deployApi(Request $request, $userId, $courseId, $liveTestId)
    {

        $validator = Validator::make($request->all(), [
            'SubCategory' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // 2. Check Enrollment
        // $enrollment = UserCourse::where('user_id', $userId)
        //     ->where('course_id', $courseId)
        //     ->first();


        // if (!$enrollment) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'User is not enrolled in this course.'
        //     ], 403);
        // }

        // 3. Check Course Feature "Digital Notes"
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.'
            ], 404);
        }

        $features = $course->features ?? [];
        if (!is_array($features) || !in_array('Live Test', $features)) {
            return response()->json([
                'success' => false,
                'message' => 'Live Test are not assigned to this course.'
            ], 403);
        }

        $liveTest = LiveTest::findOrFail($liveTestId);

        if ($liveTest->mode == 'auto') {
            $questions = Question::with(['language', 'category', 'subCategory', 'subject'])
                ->whereIn('id', $liveTest->question_ids ?? [])
                ->get();
        } else {
            $questions = LiveTestManualQuestion::with(['language', 'category', 'subCategory', 'subject'])
                ->where('live_test_id', $liveTest->id)
                ->get();
        }

        $jsonResponse = [];

        foreach ($questions as $q) {
            if (!$q->language || !$q->category || !$q->subCategory || !$q->subject)
                continue;

            $languageName = '<span class="notranslate">' . $q->language->name . '</span>';
            $categoryName = '<span class="notranslate">' . $q->category->name . '</span>';
            $subcategoryName = '<span class="notranslate">' . $q->subCategory->name . '</span>';
            $subjectName = '<span class="notranslate">' . $q->subject->name . '</span>';

            $img = '';
            if (isset($q->photo) && $q->photo != '0' && $q->photo != null) {
                $img = '<br><img src="' . url('storage/questions/' . $q->photo) . '"/>'; // Adjusted to use standard URL if possible
                // CBT uses hardcoded https://iti.online2study.in/storage/questions/, I'll match format
                if (str_contains($q->photo, 'http')) {
                    $img = '<br><img src="' . $q->photo . '"/>';
                } else {
                    $img = '<br><img src="https://iti.online2study.in/storage/questions/' . $q->photo . '"/>';
                }
            } elseif (isset($q->photo_link) && $q->photo_link) {
                $img = '<br><img src="' . $q->photo_link . '"/>';
            }

            $formattedQ = [
                'question' => '<span class="notranslate">' . $q->question . '</span>' . $img,
                'option_a' => '<span class="notranslate">' . $q->option_a . '</span>',
                'option_b' => '<span class="notranslate">' . $q->option_b . '</span>',
                'option_c' => '<span class="notranslate">' . $q->option_c . '</span>',
                'option_d' => '<span class="notranslate">' . $q->option_d . '</span>',
                'answer' => trim($q->answer),
                'notes' => ''
            ];

            if (isset($q->notes) && !empty($q->notes)) {
                $formattedQ['notes'] = '<span class="notranslate">' . $q->notes . '</span>';
            }

            if (!isset($jsonResponse[$languageName])) {
                $jsonResponse[$languageName] = [];
            }
            if (!isset($jsonResponse[$languageName][$categoryName])) {
                $jsonResponse[$languageName][$categoryName] = [];
            }
            if (!isset($jsonResponse[$languageName][$categoryName][$subcategoryName])) {
                $jsonResponse[$languageName][$categoryName][$subcategoryName] = [];
            }
            if (!isset($jsonResponse[$languageName][$categoryName][$subcategoryName][$subjectName])) {
                $jsonResponse[$languageName][$categoryName][$subcategoryName][$subjectName] = [];
            }

            $jsonResponse[$languageName][$categoryName][$subcategoryName][$subjectName][] = $formattedQ;
        }

        return response()->json($jsonResponse);
    }

    public function getLiveTestsByCourse(Request $request, $userId, $courseId)
    {
        // 1. Check User existence
        $user = \App\Models\GoogleUser::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // 2. Check Enrollment
        $enrollment = \App\Models\UserCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'User is not enrolled in this course.'
            ], 403);
        }

        // 3. Check Course Feature "Live Test"
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.'
            ], 404);
        }

        $features = $course->features ?? [];
        if (!is_array($features) || !in_array('Live Test', $features)) {
            return response()->json([
                'success' => false,
                'message' => 'Live tests are not assigned to this course.'
            ], 403);
        }

        // 4. Get Course SubCategory IDs
        $courseSubCategoryIds = array_map('strval', (array) $course->sub_category_id);

        // 5. Query LiveTests
        $liveTests = LiveTest::where('status', true)
            ->with(['language', 'category'])
            ->get();

        $matchingLiveTests = $liveTests->filter(function ($lt) use ($courseSubCategoryIds) {
            $ltSubCategoryIds = array_map('strval', (array) $lt->sub_category_id);
            return count(array_intersect($ltSubCategoryIds, $courseSubCategoryIds)) > 0;
        })->map(function ($lt) {
            $lt->photo = $lt->photo ? asset('storage/' . $lt->photo) : null;
            return $lt;
        });

        return response()->json([
            'success' => true,
            'message' => 'Live tests retrieved successfully',
            'data' => $matchingLiveTests->values()
        ]);
    }
}