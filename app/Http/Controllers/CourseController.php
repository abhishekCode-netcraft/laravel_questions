<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\GoogleUser;
use App\Models\UserCourse;
use App\Models\Category;
use App\Models\Language;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\Offer;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::all();
        $categories = Category::all();
        $subcategories = SubCategory::all();
        $subjects = Subject::all();

        // Get filters from request
        $subject_id = $request->get('subject_id');
        $subcategory_id = $request->get('sub_category_id');
        $category_id = $request->get('category_id');
        $language_id = $request->get('language_id');

        // Sorting options from request or default
        $sortColumn = $request->get('sort', 'courses.id');
        $sortDirection = $request->get('direction', 'desc');

        // Base query with joins for related names
        $query = Course::select(
            'courses.*',
            'categories.name as category_name',
            'languages.name as language_name'
        )
            ->leftJoin('categories', 'courses.category_id', '=', 'categories.id')
            ->leftJoin('languages', 'courses.language_id', '=', 'languages.id');

        // Apply filters - assuming you want to filter by exact match on these fields
        if ($subject_id) {
            $query->whereJsonContains('subject_id', $subject_id);
        }
        if ($subcategory_id) {
            $query->whereJsonContains('sub_category_id', $subcategory_id);
        }
        if ($category_id) {
            $query->where('category_id', $category_id);
        }
        if ($language_id) {
            $query->where('language_id', $language_id);
        }

        // Define sortable columns mapping
        $sortableColumns = [
            'id' => 'courses.id',
            'name' => 'courses.name',
            'language' => 'languages.name',
            'category' => 'categories.name',
        ];


        if (array_key_exists($sortColumn, $sortableColumns)) {
            $query->orderBy($sortableColumns[$sortColumn], $sortDirection);
        }

        $courses = $request->data == 'all' ? $query->get() : $query->paginate($request->data);

        $allSubcategories = SubCategory::pluck('name', 'id')->toArray();
        $allSubjects = Subject::pluck('name', 'id')->toArray();

        $dropdown_list = [
            'Select Language' => $languages,
            'Select Category' => $categories,
            'Select Sub Category' => $subcategories ?? [],
            'Select Subject' => $subjects ?? [],
        ];
        foreach ($courses as $course) {
            // Decode JSON strings safely
            $subCategoryIds = $course->sub_category_id ?? [];
            $subjectIds = $course->subject_id ?? [];

            $subCategoryIds = array_filter((array) $subCategoryIds, fn($id) => $id !== 'all');
            $subjectIds = array_filter((array) $subjectIds, fn($id) => $id !== 'all');


            $subCategoryNames = array_filter(array_map(fn($id) => $allSubcategories[$id] ?? null, $subCategoryIds));
            $subjectNames = array_filter(array_map(fn($id) => $allSubjects[$id] ?? null, $subjectIds));

            $course->sub_category_names = implode(', ', $subCategoryNames);
            $course->subject_names = implode(', ', $subjectNames);

            $course->topics_count = Topic::whereIn('subject_id', $subjectIds)->count();

            if (is_string($course->subscription)) {
                $subscriptionData = json_decode($course->subscription, true);
            } elseif (is_array($course->subscription)) {
                $subscriptionData = $course->subscription;
            } else {
                $subscriptionData = [];
            }

            if (is_array($subscriptionData)) {
                $prices = [];
                $names = [];

                foreach (['monthly', 'semi_annual', 'annual'] as $type) {
                    if (isset($subscriptionData[$type])) {
                        $amount = $subscriptionData[$type]['amount'] ?? null;

                        if (!empty($amount)) {
                            $prices[] = $amount;
                            $names[] = ucfirst(str_replace('_', ' ', $type));
                        }
                    }
                }

                $course->formatted_prices = !empty($prices) ? implode('/', $prices) : '-';
                $course->subscription_names = !empty($names) ? implode(', ', $names) : '-';
            } else {
                $course->formatted_prices = '-';
                $course->subscription_names = '-';
            }
        }

        $courseTableHeader = $this->header();

        $courseTableRow = $this->columns();

        // Return view with all required data
        return view('courses.index', compact(
            'courseTableHeader',
            'courseTableRow',
            'courses',
            'categories',
            'subcategories',
            'subjects',
            'languages',
            'subject_id',
            'subcategory_id',
            'category_id',
            'language_id',
            'sortColumn',
            'sortDirection',
            'dropdown_list'
        ));
    }

    public function header()
    {
        return [
            'id' => '#',
            'image' => 'Banner',
            'language' => 'Language Name',
            'category' => 'Category Name',
            'sub_category' => 'Sub-Category Name',
            'subject' => 'Subject Name',
            'topic' => 'Topic',
            'name' => 'Course name',
            'price' => 'Price',
            'subscription' => 'Subscription',
            'status' => 'Status',
            'action' => 'Action',
        ];
    }

    public function columns()
    {
        return [
            ['type' => 'text', 'value' => 'id'],
            ['type' => 'image', 'value' => 'banner'],
            ['type' => 'text', 'value' => 'language_name'],
            ['type' => 'text', 'value' => 'category_name'],
            ['type' => 'text', 'value' => 'sub_category_names'],
            ['type' => 'text', 'value' => 'subject_names'],
            ['type' => 'text', 'value' => 'topics_count'],
            ['type' => 'text', 'value' => 'name'],
            ['type' => 'text', 'value' => 'formatted_prices'],
            ['type' => 'text', 'value' => 'subscription_names'],
            ['type' => 'text', 'value' => 'status'],
            ['type' => 'action', 'value' => 'courses.destroy'],
        ];
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'language_id' => 'required|integer',
            'category_id' => 'required|integer',
            'subcategories' => 'required|array',
            'subjects' => 'required|array',
            'status' => 'required|boolean',
            // 'stars' => 'nullable|boolean',
            'tier' => 'nullable|string',
        ]);
        $subscriptions = [];

        foreach (['monthly', 'semi_annual', 'annual'] as $type) {
            if (isset($request->subscription[$type]['active'])) {
                $subscriptions[$type] = [
                    'amount' => $request->subscription[$type]['amount'],
                    'validity' => $request->subscription[$type]['validity'],
                ];
            }
        }

        $bannerFilename = null;
        if ($request->hasFile('banner')) {
            $file = $request->file('banner');
            $bannerFilename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/courses'), $bannerFilename);
        }

        $subjectLimit = null;
        $partLimit = null;
        if (request()->part == 'subject') {
            foreach (request()->subjects as $subjectId) {
                $subjectLimit[$subjectId] = null;
            }
        } else if (request()->part == 'part') {
            foreach (request()->subjects as $subjectId) {
                $partLimit[$subjectId] = [null, null];
            }
        }

        $course = Course::create([
            'name' => $request->name,
            'language_id' => $request->language_id,
            'category_id' => $request->category_id,
            'sub_category_id' => $request->subcategories,
            'subject_id' => $request->subjects,
            'topic_id' => $request->topics,
            'status' => $request->status,
            'subscription' => $subscriptions,
            'banner' => $bannerFilename,
            'language' => $request->language == 'on' ? 1 : 0,
            // 'question_limit' => $request->question_limit,
            'subject_limit' => $subjectLimit,
            'part_limit' => $partLimit,
            'meta_data' => $request->meta_data,
            // 'stars' => $request->stars ?? 0,
            'tier' => $request->tier ?? null,
            'features' => $request->features,
            'is_paid' => $request->is_paid ?? false,
        ]);
        return response()->json(['success' => true, 'message' => 'Course created successfully']);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string',
            'language_id' => 'required|integer',
            'category_id' => 'required|integer',
            'subcategories' => 'required|array',
            'subjects' => 'required|array',
            'status' => 'required|boolean',
            'tier' => 'nullable|string',
        ]);

        //dd(request()->all());

        $course = Course::findOrFail($id);

        $subscriptions = [];
        foreach (['monthly', 'semi_annual', 'annual'] as $type) {
            if (isset($request->subscription[$type]['active'])) {
                $subscriptions[$type] = [
                    'amount' => $request->subscription[$type]['amount'],
                    'validity' => $request->subscription[$type]['validity'],
                ];
            }
        }

        // Handle banner image update
        if ($request->hasFile('banner')) {
            // Optionally delete old image
            if ($course->banner && file_exists(public_path('uploads/courses/' . $course->banner))) {
                unlink(public_path('uploads/courses/' . $course->banner));
            }

            $file = $request->file('banner');
            $bannerFilename = time() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/courses'), $bannerFilename);
            $course->banner = $bannerFilename;
        }

        $course->name = $request->name;
        $course->language_id = $request->language_id;
        $course->category_id = $request->category_id;
        $course->sub_category_id = ($request->subcategories);
        $course->subject_id = ($request->subjects);
        $course->topic_id = ($request->topics);
        $course->status = $request->status;
        $course->subscription = $subscriptions;
        $course->language = $request->language;
        // $course->question_limit = $request->question_limit;
        $course->meta_data = $request->meta_data;
        $course->tier = $request->tier ?? null;
        $course->features = $request->features;
        $course->is_paid = $request->is_paid ?? false;

        if (request()->part == 'part') {
            $course->part_limit = $request->part_limit;
            $course->subject_limit = null; // Clear subject limit if part limit is set
        } else {
            $course->subject_limit = $request->subject_limit;
            $course->part_limit = null; // Clear part limit if subject limit is set
        }

        $course->save();

        return response()->json(['success' => true, 'message' => 'Course updated successfully']);
    }

    public function destroy(string $id)
    {
        Course::destroy($id);

        session()->flash('success', 'Offer Successfully Deleted');

        return redirect()->route('courses.index');
    }

    public function getSubjects(Request $request)
    {
        $subCategoryIds = explode(',', $request->query('ids', ''));

        $subCategoryIds = array_filter($subCategoryIds);

        $subjects = Subject::whereIn('sub_category_id', $subCategoryIds)
            ->get(['id', 'name']);

        return response()->json($subjects);
    }

    public function getCoursesWithOffers($user_id)
    {
        // Step 1: Get user
        $user = GoogleUser::find($user_id);
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // Step 2: Get user preferences
        $category_id = $user->category_id;
        $language_id = $user->language_id;

        // Step 3: Get user purchased courses IDs
        $purchasedCourseIds = UserCourse::where('user_id', $user_id)->pluck('course_id')->toArray();

        // Step 4: Get matching courses
        $courses = Course::where('category_id', $category_id)
            ->where('language_id', $language_id)
            ->get()
            ->map(function ($course) use ($user_id, $purchasedCourseIds) {

                $offer = Offer::whereJsonContains('course', (string) $course->id)
                    ->where('status', 1)
                    ->latest('created_at')
                    ->first();

                $courseSubscription = $course->subscription;
                $offerSubscription = $offer ? json_decode($offer->subscription, true) : [];

                foreach (['monthly', 'semi_annual', 'annual'] as $type) {
                    if (isset($courseSubscription[$type]['amount'])) {
                        $amount = floatval($courseSubscription[$type]['amount']);
                        $discount = 0;

                        // Step 5: Check if user purchased this course
                        if (in_array($course->id, $purchasedCourseIds)) {
                            // Apply upgrade logic here if any
                            $discount = isset($offerSubscription[$type]['upgrade'])
                                ? floatval($offerSubscription[$type]['upgrade'])
                                : 0;
                        } else {
                            // Normal discount
                            $discount = isset($offerSubscription[$type]['discount'])
                                ? floatval($offerSubscription[$type]['discount'])
                                : 0;
                        }

                        $finalAmount = $amount - (($discount / 100) * $amount);
                        $courseSubscription[$type]['final_amount'] = (int) round($finalAmount);
                    }
                }

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'is_paid' => $course->is_paid,
                    'language_id' => $course->language_id,
                    'category_id' => $course->category_id,
                    'sub_category_id' => $course->sub_category_id,
                    'subject_id' => $course->subject_id,
                    'status' => $course->status,
                    'subscription' => $courseSubscription,
                    'banner' => $course->banner,
                    'meta_data' => $course->meta_data,
                    'features' => $course->features,
                    'offer' => $offer ? [
                        'id' => $offer->id,
                        'name' => $offer->name,
                        'status' => $offer->status,
                        'banner' => $offer->banner,
                        'course' => $offer->course,
                        'subscription' => $offerSubscription,
                        'valid_from' => $offer->valid_from,
                        'valid_to' => $offer->valid_to,
                        'meta_description' => $course->meta_description,
                    ] : null,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $courses
        ]);
    }

    public function getSyllabus(Request $request, $userId, $courseId)
    {
        $subCategoryId = $request->query('SubCategory');
        if (!$subCategoryId) {
            return response()->json([
                'success' => false,
                'message' => 'SubCategory parameter is required.'
            ], 422);
        }

        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.'
            ], 404);
        }

        $subCategory = SubCategory::find($subCategoryId);
        if (!$subCategory) {
            return response()->json([
                'success' => false,
                'message' => 'SubCategory not found.'
            ], 404);
        }

        $courseSubjectIds = is_string($course->subject_id) ? json_decode($course->subject_id, true) : ($course->subject_id ?? []);
        if (!is_array($courseSubjectIds)) {
            $courseSubjectIds = [];
        }

        $subjects = Subject::where('sub_category_id', $subCategoryId)
            ->whereIn('id', $courseSubjectIds)
            ->get();

        // Get limits from course
        $subjectLimitData = is_string($course->subject_limit) ? json_decode($course->subject_limit, true) : ($course->subject_limit ?? []);
        if (!is_array($subjectLimitData)) {
            $subjectLimitData = [];
        }

        $formattedSubjects = $subjects->map(function ($subject) use ($subjectLimitData) {
            $id = (string) $subject->id;
            $limit = $subjectLimitData[$id] ?? 0;
            return [
                'subject_id' => $subject->id,
                'subject_name' => $subject->name,
                'limit' => (int) $limit
            ];
        });

        if (!$course->part_limit) {
            $courseSubjectPositions = $course->subject_limit['position'];
        } else {
            $courseSubjectPositions = $course->part_limit['position'];
        }

        $formattedSubjects = collect($formattedSubjects)
            ->sortBy(function ($item) use ($courseSubjectPositions) {
                return $courseSubjectPositions[$item['subject_id']] ?? 999;
            })
            ->values();

        // Get marks and negative marks from the first identified subject in the data
        $marks = 0;
        $negativeMarks = 0;

        $firstSubjectId = $subjects->first()->id ?? null;
        if ($firstSubjectId) {
            $id = (string) $firstSubjectId;
            if (isset($subjectLimitData['question_marks'][$id])) {
                $marks = $subjectLimitData['question_marks'][$id];
            }
            if (isset($subjectLimitData['negative_marks'][$id])) {
                $negativeMarks = $subjectLimitData['negative_marks'][$id];
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'sub_category_id' => (int) $subCategoryId,
                'sub_category_name' => $subCategory->name,
                "total_time_minutes" => 120,
                'marks_per_cushion' => (float) $marks,
                'negative_marks_per_cushion' => (float) $negativeMarks,
                'subjects' => $formattedSubjects
            ]
        ]);
    }
}