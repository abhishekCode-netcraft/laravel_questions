<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\DigitalNote;
use App\Models\Language;
use App\Models\SubCategory;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\UserCourse;
use App\Models\GoogleUser;
use App\Models\Course;

class DigitalNoteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DigitalNote::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        if ($request->has('status') && $request->status != '') {
            $query->where('status', $request->status);
        }

        // Add more filters if needed based on dropdowns

        $digitalNotes = $query->orderBy('id', 'desc')->paginate(10);
        $languages = Language::all();

        return view('digital-notes.index', compact('digitalNotes', 'languages'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'language_id' => 'required',
            'category_id' => 'required',
            // other fields
        ]);

        $data = $request->except('photo');

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/digital_notes', $filename);
            $data['photo'] = 'digital_notes/' . $filename;
        }

        DigitalNote::create($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Digital Note created successfully.']);
        }

        return redirect()->route('digital-notes.index')->with('success', 'Digital Note created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        // For API usage
        $note = DigitalNote::with(['language', 'category', 'subCategory', 'subject', 'topic'])->find($id);
        if (!$note)
            return response()->json(['error' => 'Not found'], 404);
        return response()->json($note);
    }

    public function apiIndex(Request $request)
    {
        $query = DigitalNote::query();

        if ($request->has('language_id'))
            $query->where('language_id', $request->language_id);
        if ($request->has('category_id'))
            $query->where('category_id', $request->category_id);
        if ($request->has('sub_category_id'))
            $query->where('sub_category_id', $request->sub_category_id);
        if ($request->has('subject_id'))
            $query->where('subject_id', $request->subject_id);
        if ($request->has('topic_id'))
            $query->where('topic_id', $request->topic_id);

        return response()->json($query->orderBy('id', 'desc')->get());
    }

    public function getCourseDigitalNotes(Request $request, $userId, $courseId)
    {
        // Validate request
        //$request->validate([
        //  'topic_id' => 'required|exists:topics,id'
        //]);

        // 1. Check User existence
        $user = GoogleUser::find($userId);
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.'
            ], 404);
        }

        // 2. Check Enrollment
        $enrollment = UserCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$enrollment) {
            return response()->json([
                'success' => false,
                'message' => 'User is not enrolled in this course.'
            ], 403);
        }

        // 3. Check Course Feature "Digital Notes"
        $course = Course::find($courseId);
        if (!$course) {
            return response()->json([
                'success' => false,
                'message' => 'Course not found.'
            ], 404);
        }

        $features = $course->features ?? [];
        if (!is_array($features) || !in_array('Digital Notes', $features)) {
            return response()->json([
                'success' => false,
                'message' => 'Digital notes are not assigned to this course.'
            ], 403);
        }

        // 4. Build Query
        $query = DigitalNote::with(['language', 'category', 'subCategory', 'subject', 'topic'])
            ->whereIn('topic_id', $course->topic_id);

        // 5. Sorting
        $sort = $request->get('sort', 'id');
        $order = $request->get('order', 'asc');
        if (!in_array($sort, ['id', 'created_at', 'name'])) {
            $sort = 'id';
        }
        if (!in_array(strtolower($order), ['asc', 'desc'])) {
            $order = 'asc';
        }

        $query->orderBy($sort, $order);

        // 6. Pagination - Fixed to 1 as per requirements
        $perPage = 1;

        $notes = $query->paginate($perPage);

        // 7. Format Response
        $data = [
            'current_page' => $notes->currentPage(),
            'last_page' => $notes->lastPage(),
            'per_page' => $notes->perPage(),
            'total' => $notes->total(),
            'from' => $notes->firstItem(),
            'to' => $notes->lastItem(),
            'notes' => $notes->getCollection()->map(function ($note) {
                return [
                    'id' => $note->id,
                    'name' => $note->name,
                    'photo_url' => $note->photo ? asset('storage/' . $note->photo) : null,
                    'language_id' => $note->language_id,
                    'language_name' => $note->language->name ?? null,
                    'category_id' => $note->category_id,
                    'category_name' => $note->category->name ?? null,
                    'sub_category_id' => $note->sub_category_id,
                    'sub_category_name' => $note->subCategory->name ?? null,
                    'subject_id' => $note->subject_id,
                    'subject_name' => $note->subject->name ?? null,
                    'topic_id' => $note->topic_id,
                    'topic_name' => $note->topic->name ?? null,
                    'content' => $note->content,
                    'status' => $note->status,
                    'created_at' => $note->created_at,
                    'updated_at' => $note->updated_at,
                ];
            }),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Digital notes retrieved successfully',
            'data' => $data
        ]);
    }


    public function edit($id)
    {
        $note = DigitalNote::find($id);
        if (!$note) {
            return response()->json(['error' => 'Note not found'], 404);
        }

        if (request()->wantsJson()) {
            $languages = Language::all();
            $categories = Category::where('language_id', $note->language_id)->get();
            $subCategories = SubCategory::where('category_id', $note->category_id)->get();
            $subjects = Subject::where('sub_category_id', $note->sub_category_id)->get();
            $topics = Topic::where('subject_id', $note->subject_id)->get();

            return response()->json([
                'note' => $note,
                'dropdown_list' => [
                    'languages' => $languages,
                    'categories' => $categories,
                    'subCategories' => $subCategories,
                    'subjects' => $subjects,
                    'topics' => $topics,
                ]
            ]);
        }
        return abort(404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $note = DigitalNote::findOrFail($id);

        $data = $request->except('photo');

        if ($request->hasFile('photo')) {
            $file = $request->file('photo');
            $filename = time() . '_' . $file->getClientOriginalName();
            $file->storeAs('public/digital_notes', $filename);
            $data['photo'] = 'digital_notes/' . $filename;
        }

        $note->update($data);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Digital Note updated successfully.']);
        }

        return redirect()->route('digital-notes.index')->with('success', 'Digital Note updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $note = DigitalNote::findOrFail($id);
        $note->delete();
        return redirect()->route('digital-notes.index')->with('success', 'Digital Note deleted successfully.');
    }
}
