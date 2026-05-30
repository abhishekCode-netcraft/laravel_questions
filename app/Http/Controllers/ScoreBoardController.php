<?php

namespace App\Http\Controllers;

use App\Models\UserCourse;
use App\Models\ScoreBoard;

use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\Language;
use App\Models\SubCategory;
use App\Models\QuizePractice;
use App\Models\QuestionBankCount;
use App\Models\MockTest;
use App\Models\Rank;
use App\Models\LiveTest;
use Carbon\Carbon;

class ScoreBoardController extends Controller
{

    public function store(Request $request)
    {
        $request->validate([
            'google_user_id' => 'required|exists:google_users,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'total_videos' => 'required|integer|min:0',
            'quiz_practice' => 'required|integer|min:0',
            'test_rank' => 'required|integer|min:0',
        ]);

        $scoreboard = ScoreBoard::Create($request->all());

        return response()->json(['message' => 'Scoreboard saved successfully!', 'data' => $scoreboard], 200);
    }

    public function index(Request $request)
    {
        $languages = Language::all();
        $categories = Category::all();

        $category_id = $request->get('category_id');
        $language_id = $request->get('language_id');

        $sortColumn = $request->get('sort', 'user_courses.id');
        $sortDirection = $request->get('direction', 'desc');

        // ✅ Main query
       $query = UserCourse::with([
    'user',
    'course.category.language'
])->where('user_courses.status', 1);

        // ✅ Apply filters
        if ($category_id) {
            $query->whereHas('course', function ($q) use ($category_id) {
                $q->where('category_id', $category_id);
            });
        }

        if ($language_id) {
            $query->whereHas('course.category', function ($q) use ($language_id) {
                $q->where('language_id', $language_id);
            });
        }

        // ✅ Sorting
        $sortableColumns = [
            'id' => 'user_courses.id',
            'category' => 'categories.name',
            'language' => 'languages.name',
            'course' => 'courses.name',
        ];

        if (array_key_exists($sortColumn, $sortableColumns)) {
            $query->join('courses', 'user_courses.course_id', '=', 'courses.id')
                ->join('categories', 'courses.category_id', '=', 'categories.id')
                ->join('languages', 'categories.language_id', '=', 'languages.id')
                ->orderBy($sortableColumns[$sortColumn], $sortDirection)
                ->select('user_courses.*');
        }

        $UserCourses = request()->data == 'all'
            ? $query->get()
            : $query->paginate(request()->data ?? 10);

        return view('ScoreBoard.index', compact(
            'UserCourses',
            'languages',
            'categories',
            'language_id',
            'category_id',
            'sortColumn',
            'sortDirection'
        ));
    }

    /**
     * @OA\Get(
     *     path="/api/scoreboards/{userId}",
     *     summary="Get a user's scoreboard",
     *     description="Retrieve scoreboard data by Google user ID with related user and sub-category details.",
     *     operationId="getUserScoreboard",
     *     tags={"ScoreBoard"},
     *
     *     @OA\Parameter(
     *         name="userId",
     *         in="path",
     *         required=true,
     *         description="Google user ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Scoreboard retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Scoreboard retrieved successfully!"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=15),
     *                     @OA\Property(property="google_user_id", type="integer", example=1),
     *                     @OA\Property(property="sub_category_id", type="integer", example=10),
     *                     @OA\Property(property="total_videos", type="integer", example=5),
     *                     @OA\Property(property="quiz_practice", type="integer", example=3),
     *                     @OA\Property(property="test_rank", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2025-09-05T12:34:56Z"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-05T12:34:56Z"),
     *                     @OA\Property(
     *                         property="user",
     *                         type="object",
     *                         description="Associated Google user",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="John Doe"),
     *                         @OA\Property(property="email", type="string", example="john@example.com"),
     *                     ),
     *                     @OA\Property(
     *                         property="subCategory",
     *                         type="object",
     *                         description="Associated sub-category",
     *                         @OA\Property(property="id", type="integer", example=10),
     *                         @OA\Property(property="name", type="string", example="Mathematics"),
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No scoreboard found for this user",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No scoreboard found for this user.")
     *         )
     *     )
     * )
     */
    public function show($userId)
    {
        $scoreboard = ScoreBoard::where('google_user_id', $userId)
            ->with(['user', 'subCategory'])
            ->get();

        if ($scoreboard->isEmpty()) {
            return response()->json(['message' => 'No scoreboard found for this user.'], 404);
        }

        return response()->json(['message' => 'Scoreboard retrieved successfully!', 'data' => $scoreboard], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/store/quiz",
     *     summary="Store a new quiz attempt",
     *     description="Saves a quiz attempt for a Google user with subject, topic, percentage, and attempt count.",
     *     tags={"Quize"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"google_user_id","subject_id","topic_id","percentage","attempt"},
     *             @OA\Property(property="google_user_id", type="integer", example=1, description="ID of the Google user"),
     *             @OA\Property(property="subject_id", type="integer", example=2, description="ID of the subject"),
     *             @OA\Property(property="topic_id", type="integer", example=5, description="ID of the topic"),
     *             @OA\Property(property="percentage", type="number", format="float", example=85.5, description="Score percentage"),
     *             @OA\Property(property="attempt", type="integer", example=1, description="Attempt number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quiz attempt saved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz attempt saved successfully!"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function quizeStore(Request $request)
    {
        $request->validate([
            'google_user_id' => 'required|exists:google_users,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic_id' => 'required|exists:topics,id',
            'percentage' => 'required|numeric|min:0|max:100',
        ]);

        $quiz = QuizePractice::create([
            'google_user_id' => $request->google_user_id,
            'subject_id' => $request->subject_id,
            'topic_id' => $request->topic_id,
            'percentage' => $request->percentage,

        ]);
        return response()->json([
            'message' => 'Quiz attempt created successfully!',
            'data' => $quiz
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/show/quiz/{google_user_id}/{sub_category_id}",
     *     summary="Get quiz attempts by Google User ID",
     *     description="Retrieve all quiz attempts for a specific Google user with related subject and topic details.",
     *     tags={"Quize"},
     *     @OA\Parameter(
     *         name="googleUserId",
     *         in="path",
     *         required=true,
     *         description="The ID of the Google user",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quiz attempts retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Quiz attempts retrieved successfully!"),
     *             @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No quiz attempt found for this user"
     *     )
     * )
     */
    public function quizeShow($googleUserId, $subCategoryId)
    {
        $dates = collect(range(6, 0))->map(function ($i) {
            return now()->subDays($i)->toDateString();
        });

        $records = QuizePractice::where('google_user_id', $googleUserId)
            ->whereDate('created_at', '>=', $dates->first())
            ->whereHas('subjects', function ($q) use ($subCategoryId) {
            $q->where('sub_category_id', $subCategoryId);
        })
            ->with(['subjects:id,name,sub_category_id'])
            ->get();
        $subCategoryName = SubCategory::where('id', $subCategoryId)->value('name') ?? 'Unknown SubCategory';

        if ($records->isEmpty()) {
            return response()->json([
                "message" => "No quiz records found for this user in the last 7 days.",
                "meta" => [
                    "user_id" => (int)$googleUserId,
                    "sub_category_id" => (int)$subCategoryId,
                    "sub_category_name" => $subCategoryName,
                    "range" => "weekly",
                ],
                "data" => []
            ], 200);
        }

        // Group by subject_id
        $groupedBySubject = $records->groupBy('subject_id');

        $data = $groupedBySubject->map(function ($items, $subjectId) use ($dates) {
            $subjectName = $items->first()->subjects->name ?? 'Unknown Subject';

            $summary = $dates->map(function ($date) use ($items) {
                    $dayName = Carbon::parse($date)->format('l');
                    $dailyItems = $items->filter(function ($record) use ($date) {
                            return Carbon::parse($record->created_at)->toDateString() === $date;
                        }
                        );


                        $totalRecords = $dailyItems->count();
                        $totalPercentage = $dailyItems->sum('percentage');
                        $avgPercentage = $totalRecords > 0
                            ? round($totalPercentage / $totalRecords, 2)
                            : 0;

                        return [
                        'day' => $dayName,
                        'date' => $date,
                        'percentage' => $avgPercentage,
                        'attempts' => $totalRecords
                        ];
                    }
                    )->values();

                    return [
                    'subject_id' => (int)$subjectId,
                    'subject_name' => $subjectName,
                    'summary' => $summary
                    ];
                })->values();

        return response()->json([
            "message" => "Subject-wise daily summary (all attempts included)",
            "meta" => [
                "user_id" => (int)$googleUserId,
                "sub_category_id" => (int)$subCategoryId,
                "sub_category_name" => $subCategoryName,
                "range" => "weekly",
            ],
            "data" => $data
        ], 200);
    }

    public function webquizeShow($googleUserId)
    {

        $records = QuizePractice::where('google_user_id', $googleUserId)
            ->with(['subjects:id,name,sub_category_id', 'topic:id,name'])
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No quiz attempt found for this user.',
                'data' => []
            ], 200);
        }


        $groupedBySubject = $records->groupBy('subject_id');

        $data = $groupedBySubject->map(function ($items, $subjectId) {
            $subjectName = $items->first()->subjects->name ?? 'Unknown Subject';

            $totalAttempts = $items->count();
            $totalPercentage = $items->sum('percentage');
            $avgPercentage = $totalAttempts > 0
                ? round($totalPercentage / $totalAttempts, 2)
                : 0;

            return [
            'subject_id' => (int)$subjectId,
            'subject_name' => $subjectName,
            'attempt' => $totalAttempts,
            'percentage' => $avgPercentage,
            'topic' => $items->first()->topic->name ?? 'N/A',
            'created_at' => $items->last()->created_at->toDateTimeString()
            ];
        })->values();

        return response()->json([
            'message' => 'Quiz summary calculated successfully!',
            'data' => $data
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/store/question-bank-count",
     *     summary="Create or update question bank count",
     *     description="Stores or updates the count of questions solved by a user for a specific subject and topic.",
     *     operationId="questionCountStore",
     *     tags={"Question Bank"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"google_user_id", "subject_id", "topic_id", "count"},
     *             @OA\Property(property="google_user_id", type="integer", example=1, description="ID of the Google user"),
     *             @OA\Property(property="subject_id", type="integer", example=2, description="ID of the subject"),
     *             @OA\Property(property="topic_id", type="integer", example=5, description="ID of the topic"),
     *             @OA\Property(property="count", type="integer", example=10, description="Number of questions attempted")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Question bank count stored successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Question bank count created successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="google_user_id", type="integer", example=1),
     *                 @OA\Property(property="subject_id", type="integer", example=2),
     *                 @OA\Property(property="topic_id", type="integer", example=5),
     *                 @OA\Property(property="count", type="integer", example=10),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2025-09-05T12:30:00Z"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2025-09-05T12:30:00Z")
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="google_user_id", type="array", @OA\Items(type="string", example="The selected google user id is invalid.")),
     *                 @OA\Property(property="subject_id", type="array", @OA\Items(type="string", example="The selected subject id is invalid.")),
     *                 @OA\Property(property="topic_id", type="array", @OA\Items(type="string", example="The selected topic id is invalid.")),
     *                 @OA\Property(property="count", type="array", @OA\Items(type="string", example="The count must be at least 0."))
     *             )
     *         )
     *     )
     * )
     */
    public function questionCountStore(Request $request)
    {
        $request->validate([
            'google_user_id' => 'required|exists:google_users,id',
            'subject_id' => 'required|exists:subjects,id',
            'topic_id' => 'required|exists:topics,id',
            'count' => 'required|integer|min:0',
        ]);

        $today = now()->toDateString();

        $questionBank = QuestionBankCount::where('google_user_id', $request->google_user_id)
            ->where('subject_id', $request->subject_id)
            ->where('topic_id', $request->topic_id)
            ->whereDate('created_at', $today)
            ->first();

        if ($questionBank) {
            $questionBank->update([
                'count' => $request->count,
            ]);
            $message = 'Question bank Updated successfully!';
        }
        else {
            // Agar record nahi mila to naya record banao with count = 1
            $questionBank = QuestionBankCount::create([
                'google_user_id' => $request->google_user_id,
                'subject_id' => $request->subject_id,
                'topic_id' => $request->topic_id,
                'count' => $request->count,
            ]);
            $message = 'Question bank created successfully!';
        }

        return response()->json([
            'message' => $message,
            'data' => $questionBank
        ], 200);
    }


    public function questionCountShowAllData($googleUserId)
    {
        $records = QuestionBankCount::where('google_user_id', $googleUserId)
            ->with(['user', 'subject', 'topic'])
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No question bank records found for this user.'
            ], 404);
        }

        return response()->json([
            'message' => 'Question bank records retrieved successfully!',
            'data' => $records
        ], 200);
    }


    public function questionCountShow($googleUserId, $subCategoryId)
    {
        $dates = collect(range(6, 0))->map(fn($i) => now()->subDays($i)->toDateString());

        $records = QuestionBankCount::where('google_user_id', $googleUserId)
            ->whereDate('created_at', '>=', $dates->first())
            ->whereHas('subject', fn($q) => $q->where('sub_category_id', $subCategoryId))
            ->with(['subject:id,name,sub_category_id'])
            ->get();


        $subCategoryName = SubCategory::where('id', $subCategoryId)->value('name') ?? 'Unknown SubCategory';

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No question bank records found for this user in the last 7 days.',
                'meta' => [
                    'user_id' => (int)$googleUserId,
                    'sub_category_id' => (int)$subCategoryId,
                    'sub_category_name' => $subCategoryName,
                    'range' => 'weekly',
                ],
                'labels' => $dates,
                'series' => []
            ], 200);
        }

        $grouped = $records->groupBy('subject_id');

        $series = $grouped->map(function ($items, $subjectId) use ($dates) {
            $subjectName = $items->first()->subject->name ?? 'Unknown Subject';

            $data = $dates->map(function ($date) use ($items) {
                    return $items->whereBetween('created_at', [
                    $date . " 00:00:00",
                    $date . " 23:59:59"
                    ])->sum('count');
                }
                );

                return [
                'subject_id' => (int)$subjectId,
                'subject_name' => $subjectName,
                'data' => $data
                ];
            })->values();

        return response()->json([
            'message' => 'Question bank records retrieved successfully!',
            'meta' => [
                'user_id' => (int)$googleUserId,
                'sub_category_id' => (int)$subCategoryId,
                'sub_category_name' => $subCategoryName,
                'range' => 'weekly',
            ],
            'labels' => $dates,
            'series' => $series
        ], 200);
    }


    // public function questioncountshow($googleUserId)
    // {

    //     $dates = collect(range(6, 0))->map(function ($i) {
    //         return now()->subDays($i)->toDateString();
    //     });

    //     $records = QuestionBankCount::where('google_user_id', $googleUserId)
    //         ->whereDate('created_at', '>=', $dates->first())
    //         ->get();

    //     if ($records->isEmpty()) {
    //         return response()->json([
    //             'message' => 'No question bank records found for this user in the last 7 days.',
    //             'labels' => $dates,
    //             'series' => []
    //         ], 200);
    //     }

    //     $grouped = $records->groupBy('subject_id');

    //     $series = $grouped->map(function ($items, $subjectId) use ($dates) {
    //         $data = $dates->map(function ($date) use ($items) {

    //             return $items->whereBetween('created_at', [
    //                 $date . " 00:00:00",
    //                 $date . " 23:59:59"
    //             ])->sum('count');
    //         });

    //         return [
    //             'subject_id' => (int) $subjectId,
    //             'data' => $data
    //         ];
    //     })->values();

    //     return response()->json([
    //         'message' => 'Question bank records retrieved successfully!',
    //         'labels' => $dates,
    //         'series' => $series
    //     ], 200);
    // }




    /**
     * @OA\Post(
     *     path="/api/store/mock-test",
     *     summary="Create a mock test record",
     *     description="Store a new mock test record for a user with details like right/wrong answers, attempts, and time taken.",
     *     operationId="mockTestStore",
     *     tags={"Mock Test"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"google_user_id", "sub_category_id", "right_answer", "wrong_answer", "attempt", "time_taken"},
     *             @OA\Property(property="google_user_id", type="integer", example=1),
     *             @OA\Property(property="sub_category_id", type="integer", example=10),
     *             @OA\Property(property="right_answer", type="integer", example=15),
     *             @OA\Property(property="wrong_answer", type="integer", example=5),
     *             @OA\Property(property="attempt", type="integer", example=20),
     *             @OA\Property(property="time_taken", type="integer", example=1200, description="Time taken in seconds")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Mock test created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Mock test saved successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="google_user_id", type="integer", example=1),
     *                 @OA\Property(property="sub_category_id", type="integer", example=10),
     *                 @OA\Property(property="right_answer", type="integer", example=15),
     *                 @OA\Property(property="wrong_answer", type="integer", example=5),
     *                 @OA\Property(property="attempt", type="integer", example=20),
     *                 @OA\Property(property="time_taken", type="integer", example=1200)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function mockTestStore(Request $request)
    {
        $request->validate([
            'google_user_id' => 'required|exists:google_users,id',
            'sub_category_id' => 'required|exists:sub_categories,id',
            'right_answer' => 'required|integer|min:0',
            'wrong_answer' => 'required|integer|min:0',
            'total_questions' => 'required|integer|min:0',
            'time_taken' => 'required|integer|min:0',
        ]);

        $mock = MockTest::create([
            'google_user_id' => $request->google_user_id,
            'sub_category_id' => $request->sub_category_id,
            'right_answer' => $request->right_answer,
            'wrong_answer' => $request->wrong_answer,
            'total_questions' => $request->total_questions,
            'time_taken' => $request->time_taken,
        ]);

        return response()->json([
            'message' => 'Mock test saved successfully!',
            'data' => $mock
        ], 200);
    }



    /**
     * @OA\Get(
     *     path="/api/show/mock-test/{google_user_id}/{sub_category_id}",
     *     summary="Get mock test records by user",
     *     description="Retrieve all mock test records for a given user, including related user and subcategory.",
     *     operationId="mockTestShow",
     *     tags={"Mock Test"},
     *
     *     @OA\Parameter(
     *         name="google_user_id",
     *         in="path",
     *         required=true,
     *         description="ID of the Google user",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Mock test records retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Mock test records retrieved successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="google_user_id", type="integer", example=1),
     *                     @OA\Property(property="sub_category_id", type="integer", example=10),
     *                     @OA\Property(property="right_answer", type="integer", example=15),
     *                     @OA\Property(property="wrong_answer", type="integer", example=5),
     *                     @OA\Property(property="attempt", type="integer", example=20),
     *                     @OA\Property(property="time_taken", type="integer", example=1200)
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No mock test records found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No mock test records found for this user.")
     *         )
     *     )
     * )
     */
    public function mockTestShow($googleUserId, $subCategoryId)
    {
        $records = MockTest::where('google_user_id', $googleUserId)
            ->where('sub_category_id', $subCategoryId)
            ->with(['user', 'subCategory:id,name'])
            ->get();


        $subCategoryName = SubCategory::where('id', $subCategoryId)->value('name') ?? 'Unknown SubCategory';


        if ($records->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No mock test records found for this user and sub-category.',
                'data' => null,
                'meta' => [
                    'user_id' => (int)$googleUserId,
                    'sub_category_id' => (int)$subCategoryId,
                    'sub_category_name' => $subCategoryName,
                ],
            ], 404);
        }

        $rightAnswerSum = $records->sum('right_answer');
        $wrongAnswerSum = $records->sum('wrong_answer');
        $totalQuestionsSum = $records->sum('total_questions');
        $totalTimeTaken = $records->sum('time_taken');
        $unanswered = $totalQuestionsSum - ($rightAnswerSum + $wrongAnswerSum);
        $attemptNumber = $records->count();
        $averageTimeTaken = $attemptNumber > 0 ? round($totalTimeTaken / $attemptNumber, 2) : 0;

        $latestRecord = $records->sortByDesc('created_at')->first();

        $data = [
            'id' => $latestRecord->id,
            'google_user_id' => (int)$googleUserId,
            'sub_category_id' => (int)$subCategoryId,
            'sub_category_name' => $subCategoryName,
            'right_answer' => $rightAnswerSum,
            'wrong_answer' => $wrongAnswerSum,
            'total_questions' => $totalQuestionsSum,
            'unanswered' => $unanswered,
            'time_taken' => $averageTimeTaken,
            'attempt_number' => $attemptNumber,
            'created_at' => $latestRecord->created_at,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Mock test records retrieved successfully!',
            'data' => $data
        ], 200);
    }

    public function webmockTestShow($googleUserId)
    {

        $records = MockTest::where('google_user_id', $googleUserId)
            ->with(['user', 'subCategory'])
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No mock test records found for this user.'
            ], 404);
        }


        $grouped = $records->groupBy('sub_category_id');

        $data = $grouped->map(function ($tests, $subCategoryId) {
            $subCategoryName = $tests->first()->subCategory->name ?? 'Unknown SubCategory';

            $rightAnswerSum = $tests->sum('right_answer');
            $wrongAnswerSum = $tests->sum('wrong_answer');
            $totalQuestionsSum = $tests->sum('total_questions');
            $totalTimeTaken = $tests->sum('time_taken');
            $attemptNumber = $tests->count();
            // $averageTimeTaken = $attemptNumber > 0 ? round($totalTimeTaken / $attemptNumber, 2) : 0;
            $latestRecord = $tests->sortByDesc('created_at')->first();

            return [
            'id' => $latestRecord->id,
            'google_user_id' => (int)$latestRecord->google_user_id,
            'sub_category_id' => (int)$subCategoryId,
            'sub_category' => [
            'id' => (int)$subCategoryId,
            'name' => $subCategoryName,
            ],
            'right_answer' => $rightAnswerSum,
            'wrong_answer' => $wrongAnswerSum,
            'total_questions' => $totalQuestionsSum,
            'attempt' => $attemptNumber,
            'time_taken' => $totalTimeTaken,
            'created_at' => $latestRecord->created_at,
            ];
        })->values();

        return response()->json([
            'message' => 'Mock test records retrieved successfully!',
            'data' => $data
        ], 200);
    }

    public function rankStore(Request $request)
    {
        $request->validate([
            'google_user_id' => 'required|exists:google_users,id',
            'live_test_id' => 'required|exists:live_tests,id',
            'right_answer' => 'required|integer|min:0',
            'wrong_answer' => 'required|integer|min:0',
            'total_questions' => 'required|integer|min:0',
            'time_taken' => 'required|integer|min:0',
        ]);

        $rank = Rank::create([
            'google_user_id' => $request->google_user_id,
            'live_test_id' => $request->live_test_id,
            'right_answer' => $request->right_answer,
            'wrong_answer' => $request->wrong_answer,
            'total_questions' => $request->total_questions,
            'time_taken' => $request->time_taken,
        ]);

        return response()->json([
            'message' => 'Rank saved successfully!',
            'data' => $rank
        ], 200);
    }

    public function rankShow($googleUserId, $liveTestId)
    {
        $records = Rank::where('google_user_id', $googleUserId)
            ->where('live_test_id', $liveTestId)
            ->with(['user', 'liveTest:id,title'])
            ->get();

        $liveTestName = LiveTest::where('id', $liveTestId)->value('title') ?? 'Unknown Live Test';

        if ($records->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No rank records found for this user and live test.',
                'data' => null,
                'meta' => [
                    'user_id' => (int)$googleUserId,
                    'live_test_id' => (int)$liveTestId,
                    'live_test_name' => $liveTestName,
                ],
            ], 404);
        }

        $rightAnswerSum = $records->sum('right_answer');
        $wrongAnswerSum = $records->sum('wrong_answer');
        $totalQuestionsSum = $records->sum('total_questions');
        $totalTimeTaken = $records->sum('time_taken');
        $attemptNumber = $records->count();
        $averageTimeTaken = $attemptNumber > 0 ? round($totalTimeTaken / $attemptNumber, 2) : 0;

        $rankValue = $attemptNumber > 0 ? round($rightAnswerSum / $attemptNumber, 2) : 0;

        $latestRecord = $records->sortByDesc('created_at')->first();

        $data = [
            'id' => $latestRecord->id,
            'google_user_id' => (int)$googleUserId,
            'live_test_id' => (int)$liveTestId,
            'live_test_name' => $liveTestName,
            'right_answer' => $rightAnswerSum,
            'wrong_answer' => $wrongAnswerSum,
            'total_questions' => $totalQuestionsSum,
            'time_taken' => $averageTimeTaken,
            'attempt_number' => $attemptNumber,
            'rank' => $rankValue,
            'created_at' => $latestRecord->created_at,
        ];

        return response()->json([
            'status' => true,
            'message' => 'Rank records retrieved successfully!',
            'data' => $data
        ], 200);
    }

    public function webrankShow($googleUserId)
    {
        $records = Rank::where('google_user_id', $googleUserId)
            ->with(['user', 'liveTest'])
            ->get();

        if ($records->isEmpty()) {
            return response()->json([
                'message' => 'No rank records found for this user.'
            ], 404);
        }

        $grouped = $records->groupBy('live_test_id');

        $data = $grouped->map(function ($tests, $liveTestId) {
            $liveTestName = $tests->first()->liveTest->title ?? 'Unknown Live Test';

            $rightAnswerSum = $tests->sum('right_answer');
            $wrongAnswerSum = $tests->sum('wrong_answer');
            $totalQuestionsSum = $tests->sum('total_questions');
            $totalTimeTaken = $tests->sum('time_taken');
            $attemptNumber = $tests->count();
            $latestRecord = $tests->sortByDesc('created_at')->first();

            return [
            'id' => $latestRecord->id,
            'google_user_id' => (int)$latestRecord->google_user_id,
            'live_test_id' => (int)$liveTestId,
            'live_test' => [
            'id' => (int)$liveTestId,
            'name' => $liveTestName,
            ],
            'right_answer' => $rightAnswerSum,
            'wrong_answer' => $wrongAnswerSum,
            'total_questions' => $totalQuestionsSum,
            'attempt' => $attemptNumber,
            'time_taken' => $totalTimeTaken,
            'created_at' => $latestRecord->created_at,
            ];
        })->values();

        return response()->json([
            'message' => 'Rank records retrieved successfully!',
            'data' => $data
        ], 200);
    }
}