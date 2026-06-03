<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Language;
use App\Models\Course;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CategoryExport;
use App\Exports\SampleCategoryExport;
use App\Imports\CategoryImport;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::all();
        $query = Category::with('language')->withCount('question');

        $sortColumn = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'asc');

        if ($sortColumn === 'language') {
            $query = $query->join('languages', 'categories.language_id', '=', 'languages.id')
                ->select('categories.*', 'languages.name as language_name')
                ->orderBy('language_name', $sortDirection);
        } elseif (in_array($sortColumn, ['id', 'name'])) {
            $query = $query->orderBy($sortColumn, $sortDirection);
        }

        if ($language_id = $request->get('language_id')) {
            $query = $query->where('language_id', $language_id);
        }

        if (request()->data == 'all') {
            $categorys = $query->get();
        } else {
            $categorys = $query->paginate(request()->data);
        }

        return view('categorys.index', compact('categorys', 'languages', 'sortColumn', 'sortDirection', 'language_id'));
    }


    public function export(Request $request)
    {
        $languageId = $request->get('language_id');

        return Excel::download(new CategoryExport($languageId), 'categories.xlsx');
    }


    public function sample()
    {
        return Excel::download(new SampleCategoryExport, 'SampleCategories.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new CategoryImport();

        try {
            // $ids = [];
            $rows = Excel::import($importer, $request->file('file'));

            foreach ($rows as $key => $row) {
                $rowCount = $key + 2;

                // if (!empty($row['id'])) {
                //     if (in_array($row['id'], $ids)) {
                //         return back()->with('error', 'Row: ' . $rowCount . ' - Duplicate ID found: ' . $row['id']);
                //     }

                //     $ids[] = $row['id']; // Store ID in array
                // }

                if (empty($row['language_id'])) {
                    return back()->with('error', 'Row: ' . $rowCount . '- Subcategory is required.');
                }

                if (!$this->getLanguageId($row['language_id'])) {
                    return back()->with('error', 'Subcategory - "' . $row['language_id'] . '" not available');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('category.index')
                ->with('import_errors', [$e->getMessage()]);
        }

        if (!empty($importer->errors)) {
            return redirect()->route('category.index')
                ->with('import_errors', $importer->errors);
        }

        return redirect()->route('category.index')->with('success', 'Categories imported successfully!');
    }

    private function getLanguageId($id)
    {
        return \App\Models\Language::find($id)->id ?? null;
    }

    public function create()
    {
        $languages = Language::all();

        return view('categorys.create', compact('languages'));
    }

    public function store(Request $request)
    {
        request()->validate([
            'name' => 'required',
            'language_id' => 'required'
        ]);

        $category = Category::create(request()->all());
        // if ($request->hasFile('photo')) {
        //     $fileName = "category/" . time() . "_photo.jpg";
        //     $request->file('photo')->storePubliclyAs('public', $fileName);
        //     $category->photo = $fileName;
        //     $category->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "category/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/category")) {
                mkdir("storage/category", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $category->photo = $fileName;
                $category->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'Successfully Created', 'category' => $category]);
    }

    public function edit(string $id)
    {
        $category = Category::find($id);

        $languages = Language::all();

        return view('categorys.edit', compact('category', 'languages'));
    }


    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required',
            'language_id' => 'required'
        ]);

        $category = Category::find($id);

        $category->update(request()->all());

        // if ($request->hasFile('photo')) {
        // $fileName = "category/" . time() . "_photo.jpg";
        // $stored = $request->file('photo')->storeAs('public', $fileName);
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "category/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/category")) {
                mkdir("storage/category", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $category->photo = $fileName;
                $category->save();
            }
        }
        // }

        return response()->json(['success' => true, 'message' => 'Successfully Updated', 'category' => $category]);
    }


    public function show(string $id)
    {
        //
    }


    public function destroy(string $id)
    {
        Category::find($id)
            ->delete();

        session()->flash('success', 'Category Successfully Deleted');

        return redirect()->route('category.index');
    }

    /**
     * @OA\Get(
     *     path="/api/categories/language/{language_id}",
     *     summary="Get categories by language",
     *     description="Retrieve all categories for a given language along with their subcategories, subjects, and topics.",
     *     operationId="getCategoriesByLanguage",
     *     tags={"Categories"},
     *
     *     @OA\Parameter(
     *         name="language_id",
     *         in="path",
     *         required=true,
     *         description="ID of the language",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Categories retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Science"),
     *                     @OA\Property(property="language_id", type="integer", example=1),
     *                     @OA\Property(
     *                         property="subcategory",
     *                         type="array",
     *                         @OA\Items(
     *                             @OA\Property(property="id", type="integer", example=10),
     *                             @OA\Property(property="name", type="string", example="Physics"),
     *                             @OA\Property(
     *                                 property="subject",
     *                                 type="array",
     *                                 @OA\Items(
     *                                     @OA\Property(property="id", type="integer", example=100),
     *                                     @OA\Property(property="name", type="string", example="Mechanics"),
     *                                     @OA\Property(
     *                                         property="topic",
     *                                         type="array",
     *                                         @OA\Items(
     *                                             @OA\Property(property="id", type="integer", example=1000),
     *                                             @OA\Property(property="name", type="string", example="Newton's Laws")
     *                                         )
     *                                     )
     *                                 )
     *                             )
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=404,
     *         description="No categories found for this language ID",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No categories found for this language ID.")
     *         )
     *     )
     * )
     */
    public function getCategoriesByLanguage($language_id, $couseId)
    {
        $course = Course::find($couseId);

        $subjectIds = $course->subject_id;
        $topicIds = $course->topic_id;

        $query = Category::with([
            'subcategory.subject' => function ($q) use ($subjectIds, $topicIds) {
                $q->whereIn('id', $subjectIds)
                    ->with([
                        'topic' => function ($topicQuery) use ($topicIds) {
                            $topicQuery->whereIn('id', $topicIds);
                        }
                    ]);
            },
            'subcategory.subject.topic'
        ])
            ->where('language_id', $language_id);


        $categories = $query->get();

        if ($categories->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No categories found for this language ID.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }
}
