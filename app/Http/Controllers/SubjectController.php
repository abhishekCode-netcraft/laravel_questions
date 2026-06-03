<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Language;
use Illuminate\Http\Request;
use App\Models\Subject;
use App\Models\SubCategory;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SubjectExport;
use App\Exports\SampleSubjectExport;
use App\Imports\SubjectImport;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::all();
        $categories = Category::all();
        $subcategories = SubCategory::all();

        $language_id = $request->get('language_id');
        $category_id = $request->get('category_id');
        $subcategory_id = $request->get('sub_category_id');

        // Sorting logic
        $sortColumn = $request->get('sort', 'id'); // Default column
        $sortDirection = $request->get('direction', 'asc'); // Default direction

        $query = Subject::withCount('question');

        if ($subcategory_id) {
            $query->where('sub_category_id', $subcategory_id);
        }
        if ($category_id) {
            $query->whereHas('subCategory', function ($query) use ($category_id) {
                $query->where('category_id', $category_id);
            });
        }
        if ($language_id) {
            $query->whereHas('subCategory.category', function ($query) use ($language_id) {
                $query->where('language_id', $language_id);
            });
        }

        // Handle sorting for related fields
        if (in_array($sortColumn, ['id', 'name', 'sub_category', 'category', 'language'])) {
            $query->with(['subCategory.category.language']);
            $query->leftJoin('sub_categories', 'subjects.sub_category_id', '=', 'sub_categories.id')
                ->leftJoin('categories', 'sub_categories.category_id', '=', 'categories.id')
                ->leftJoin('languages', 'categories.language_id', '=', 'languages.id');

            $query->orderBy(
                match ($sortColumn) {
                    'language' => 'languages.name',
                    'category' => 'categories.name',
                    'sub_category' => 'sub_categories.name',
                    default => 'subjects.' . $sortColumn,
                },
                $sortDirection
            );
        }

        if (request()->data == 'all') {
            $subjects = $query->get();
        } else {
            $subjects = $query->paginate(request()->data);
        }

        $dropdown_list = [
            'Select Language' => $languages,
            'Select Category' => [],
            'Select Sub Category' => [],
        ];

        return view('subjects.index', compact(
            'subjects',
            'categories',
            'subcategories',
            'languages',
            'language_id',
            'category_id',
            'subcategory_id',
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

        return Excel::download(new SubjectExport($languageId, $categoryId, $subCategoryId), 'Subject.xlsx');
    }

    public function sample()
    {
        return Excel::download(new SampleSubjectExport, 'SampleSubject.xlsx');
    }


    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new SubjectImport();

        try {
            $rows = Excel::import($importer, $request->file('file'));

            foreach ($rows as $key => $row) {
                $rowCount = $key + 2;

                if (empty($row['sub_category_id'])) {
                    return back()->with('error', 'Row: ' . $rowCount . '- Subcategory is required.');
                }
                if (!$this->getSubCategoryId($row['sub_category_id'])) {
                    return back()->with('error', 'Subcategory - "' . $row['sub_category_id'] . '" not available');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('subject.index')
                ->with('import_errors', [$e->getMessage()]);
        }

        if (!empty($importer->errors)) {
            return redirect()->route('subject.index')
                ->with('import_errors', $importer->errors);
        }

        return redirect()->route('subject.index')->with('success', 'Subject imported successfully!');
    }

    private function getSubCategoryId($id)
    {
        return \App\Models\SubCategory::find($id)->id ?? null;
    }

    public function create()
    {
        $sub_categories = SubCategory::all();

        $languages = Language::all();

        return view('subjects.create', compact('sub_categories', 'languages'));
    }

    public function store(Request $request)
    {
        request()->validate([
            'name' => 'required',
            'sub_category_id' => 'required'
        ]);

        $subject = Subject::create(request()->all());

        // if ($request->hasFile('photo')) {
        //     $fileName = "subject/" . time() . "_photo.jpg";

        //     $request->file('photo')->storePubliclyAs('public', $fileName);

        //     $subject->photo = $fileName;

        //     $subject->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "subject/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/subject")) {
                mkdir("storage/subject", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $subject->photo = $fileName;
                $subject->save();
            }
        }

        // session()->flash('success', 'Subject Successfully Created');
        return response()->json(['success' => true, 'message' => 'Subject Successfully Created', 'subject' => $subject]);
        // return redirect()->route('subject.index');
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
        $subject = Subject::with('subCategory')
            ->find($id);

        $sub_categories = SubCategory::all();

        $languages = Language::all();

        return view('subjects.edit', compact('subject', 'sub_categories', 'languages'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        request()->validate([
            'name' => 'required',
            'sub_category_id' => 'required'
        ]);

        $subject = Subject::find($id);
        $subject->update(request()->all());

        // if ($request->hasFile('photo')) {
        //     $fileName = "subject/" . time() . "_photo.jpg";

        //     $request->file('photo')->storePubliclyAs('public', $fileName);

        //     $subject->photo = $fileName;

        //     $subject->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "subject/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/subject")) {
                mkdir("storage/subject", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $subject->photo = $fileName;
                $subject->save();
            }
        }


        // session()->flash('success', 'Subject Successfully Updated');
        return response()->json(['success' => true, 'message' => 'Subject Successfully Updated', 'subject' => $subject]);
        // return redirect()->route('subject.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Subject::find($id)
            ->delete();

        session()->flash('success', 'Subject Successfully Deleted');

        return redirect()->route('subject.index');
    }
}
