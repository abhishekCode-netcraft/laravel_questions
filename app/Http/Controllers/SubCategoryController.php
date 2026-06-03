<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Language;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SubCategoryExport;
use App\Exports\SampleSubCategoryExport;
use App\Imports\SubCategoryImport;
use App\Models\Offer;

/**
 * @OA\Schema(
 *     schema="SubCategory",
 *     type="object",
 *     title="SubCategory",
 *     required={"id", "name"},
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="description", type="string", example="All kinds of electronics"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T13:00:00Z")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Offer",
 *     type="object",
 *     title="Offer",
 *     required={"id", "title", "sub_category_id"},
 *     @OA\Property(property="id", type="integer", example=101),
 *     @OA\Property(property="title", type="string", example="20% Discount on Accessories"),
 *     @OA\Property(property="sub_category_id", type="integer", example=1),
 *     @OA\Property(property="valid_till", type="string", format="date", example="2025-12-31"),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-01T12:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-01T13:00:00Z")
 * )
 */
class SubCategoryController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::all();
        $categories = Category::all();

        $language_id = $request->get('language_id');
        $category_id = $request->get('category_id');

        // Sorting logic
        $sortColumn = $request->get('sort', 'id'); // Default column
        $sortDirection = $request->get('direction', 'asc'); // Default direction

        $query = SubCategory::with('category.language', 'question');

        if ($category_id) {
            $query->where('category_id', $category_id);
        }

        if ($language_id) {
            $query->whereHas('category', function ($query) use ($language_id) {
                $query->where('language_id', $language_id);
            });
        }

        // Handle sorting for related fields
        if (in_array($sortColumn, ['id', 'name', 'category', 'language'])) {
            $query->join('categories', 'sub_categories.category_id', '=', 'categories.id')
                ->join('languages', 'categories.language_id', '=', 'languages.id')
                ->select('sub_categories.*')
                ->orderBy(
                    match ($sortColumn) {
                        'language' => 'languages.name',
                        'category' => 'categories.name',
                        default => 'sub_categories.' . $sortColumn,
                    },
                    $sortDirection
                );
        }

        if (request()->data == 'all') {
            $sub_categories = $query->get();
        } else {
            $sub_categories = $query->paginate(request()->data);
        }

        $dropdown_list = [
            'Select Language' => $languages,
            'Select Category' => [],
        ];

        return view('sub-category.index', compact(
            'sub_categories',
            'categories',
            'languages',
            'language_id',
            'category_id',
            'sortColumn',
            'sortDirection',
            'dropdown_list'
        ));
    }

    public function export(Request $request)
    {
        $languageId = $request->get('language_id');
        $categoryId = $request->get('category_id');

        return Excel::download(new SubCategoryExport($languageId,  $categoryId), 'Subcategories.xlsx');
    }

    public function sample()
    {
        return Excel::download(new SampleSubCategoryExport, 'SampleSubCategories.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new SubCategoryImport();

        try {
            $rows = Excel::import($importer, $request->file('file'));

            foreach ($rows as $key => $row) {
                $rowCount = $key + 2;

                if (empty($row['category_id'])) {
                    return back()->with('error', 'Row: ' . $rowCount . '- Subcategory is required.');
                }

                if (!$this->getCategoryId($row['category_id'])) {
                    return back()->with('error', 'Subcategory - "' . $row['category_id'] . '" not available');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('sub-category.index')
                ->with('import_errors', [$e->getMessage()]);
        }

        if (!empty($importer->errors)) {
            return redirect()->route('sub-category.index')
                ->with('import_errors', $importer->errors);
        }

        return redirect()->route('sub-category.index')->with('success', 'Sub Categories imported successfully!');
    }

    private function getCategoryId($id)
    {
        return \App\Models\Category::find($id)->id ?? null;
    }

    public function create()
    {
        $categories = Category::all();

        $languages = Language::all();

        return view('sub-category.create', compact('categories', 'languages'));
    }


    public function store(Request $request)
    {
        // Validate incoming request
        $request->validate([
            'category_id' => 'required',
            'name' => 'required',

        ]);

        // Prepare data for insertion
        $data = $request->only(['category_id', 'name', 'plan_type', 'status', 'parent_id']);

        // Convert plans array to JSON string
        $data['plans'] = json_encode($request->plans);

        // Create subcategory with basic info
        $subcategory = SubCategory::create($data);

        // Handle image upload if exists
        // if ($request->hasFile('photo')) {
        //     $fileName = "sub_category/" . time() . "_photo.jpg";
        //     $request->file('photo')->storePubliclyAs('public', $fileName);
        //     $subcategory->photo = $fileName;
        //     $subcategory->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "sub_category/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/sub_category")) {
                mkdir("storage/sub_category", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $subcategory->photo = $fileName;
                $subcategory->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'SubCategory created successfully with plan details!',
            'subcategory' => $subcategory
        ]);
    }

    public function edit(string $id)
    {
        $sub_categories = SubCategory::find($id);

        $categories = Category::all();

        $languages = Language::all();

        return view('sub-category.edit', compact('sub_categories', 'categories', 'languages'));
    }


    public function update(Request $request, string $id)
    {
        // Validate incoming request
        $request->validate([
            'category_id' => 'required',
            'name' => 'required',
        ]);

        // Find the subcategory
        $subcategory = SubCategory::findOrFail($id);

        // Prepare the data for update
        $data = $request->only(['category_id', 'name', 'plan_type', 'status', 'parent_id']);

        // Convert plans array to JSON string if provided
        if ($request->has('plans')) {
            $data['plans'] = json_encode($request->plans);
        }

        // Update subcategory
        $subcategory->update($data);

        // // Handle image upload if exists
        // if ($request->hasFile('photo')) {
        //     $fileName = "sub_category/" . time() . "_photo.jpg";
        //     $request->file('photo')->storePubliclyAs('public', $fileName);
        //     $subcategory->photo = $fileName;
        //     $subcategory->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "sub_category/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/sub_category")) {
                mkdir("storage/sub_category", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $subcategory->photo = $fileName;
                $subcategory->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'SubCategory updated successfully!',
            'subcategory' => $subcategory
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/sub/category/details/{id}",
     *     summary="Get sub-category details with associated offers",
     *     description="Returns details of a specific sub-category along with all its related offers",
     *     operationId="getSubCategoryDetailsWithOffers",
     *     tags={"Sub Categories"},
     *     
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="ID of the sub-category",
     *         @OA\Schema(type="integer")
     *     ),
     *     
     *     @OA\Response(
     *         response=200,
     *         description="Sub Category Details with Offers",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Sub Category Details with Offers"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(
     *                     property="sub_category",
     *                     type="object",
     *                     description="SubCategory object",
     *                 ),
     *                 @OA\Property(
     *                     property="offers",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Offer")
     *                 )
     *             )
     *         )
     *     ),
     *     
     *     @OA\Response(
     *         response=404,
     *         description="Sub Category not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Sub Category not found")
     *         )
     *     )
     * )
     */
    public function getSubCategoryDetailsWithOffers($id)
    {
        $subCategory = SubCategory::where('category_id', $id)
            ->get();

        if (!$subCategory) {
            return response()->json([
                'status' => false,
                'message' => 'Sub Category not found'
            ], 404);
        }

        foreach ($subCategory as $key => $subCat) {
            $offers = Offer::where('sub_category_id', $subCat->id)->first();

            $subCategory[$key]['offers'] = $offers;
        }


        return response()->json([
            'status' => true,
            'message' => 'Sub Category Details with Offers',
            'data' => [
                'sub_category' => $subCategory,
            ]
        ]);
    }

    public function destroy(string $id)
    {
        SubCategory::find($id)
            ->delete();

        return redirect()->route('sub-category.index');
    }
}
