<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Language;
use App\Models\SubCategory;
use Illuminate\Http\Request;

use App\Models\Topic;
use App\Models\Subject;

use Maatwebsite\Excel\Facades\Excel;
use App\Exports\topicsExport;
use App\Exports\SampletopicsExport;
use App\Imports\topicsImport;

class TopicController extends Controller
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

        $subject_id = $request->get('subject_id');
        $subcategory_id = $request->get('sub_category_id');
        $category_id = $request->get('category_id');
        $language_id = $request->get('language_id');
       
        $sortColumn = $request->get('sort', 'topics.id');
        $sortDirection = $request->get('direction', 'desc');

        // Prepare the query
        $query = Topic::select('topics.*')->with('subject.subCategory.category.language', 'question');

        if ($subject_id) {
            $query->where('subject_id', $subject_id);
        }
        if ($subcategory_id) {
            $query->whereHas('subject', function ($q) use ($subcategory_id) {
                $q->where('sub_category_id', $subcategory_id);
            });
        }
        if ($category_id) {
            $query->whereHas('subject.subCategory', function ($q) use ($category_id) {
                $q->where('category_id', $category_id);
            });
        }
        if ($language_id) {
            $query->whereHas('subject.subCategory.category', function ($q) use ($language_id) {
                $q->where('language_id', $language_id);
            });
        }

        // Validate sort column
        $sortableColumns = ['id' => 'topics.id', 'name' => 'topics.name', 'language' => 'languages.name', 'category' => 'categories.name', 'sub_category' => 'sub_categories.name', 'subject' => 'subjects.name'];

        if (array_key_exists($sortColumn, $sortableColumns)) {
            // Include necessary joins for sorting by related columns
            $query->join('subjects', 'topics.subject_id', '=', 'subjects.id')
                ->join('sub_categories', 'subjects.sub_category_id', '=', 'sub_categories.id')
                ->join('categories', 'sub_categories.category_id', '=', 'categories.id')
                ->join('languages', 'categories.language_id', '=', 'languages.id');

            // Apply the sorting using the proper table aliases
            $query->orderBy($sortableColumns[$sortColumn], $sortDirection);
        }

        if (request()->data == 'all') {
            $topics = $query->get();
        } else {
            $topics = $query->paginate(request()->data);
        }

        $dropdown_list = [
            'Select Language' => $languages,
            'Select Category' => [],
            'Select Sub Category' => [],
            'Select Subject' => [],
        ];

        // Return the view with all necessary data
        return view('topics.index', compact('topics', 'categories', 'subcategories', 'subjects', 'languages', 'subject_id', 'subcategory_id', 'category_id', 'language_id', 'sortColumn', 'sortDirection', 'dropdown_list'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        request()->validate([
            'name' => 'required',
            'subject_id' => 'required'
        ]);

        $topic = Topic::create(request()->all());

        // if ($request->hasFile('photo')) {
        //     $fileName = "topic/" . time() . "_photo.jpg";
        //     $request->file('photo')->storePubliclyAs('public', $fileName);
        //     $topic->photo = $fileName;
        //     $topic->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "topic/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/topic")) {
                mkdir("storage/topic", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $topic->photo = $fileName;
                $topic->save();
            }
        }

        session()->flash('success', 'Topic Successfully Created');

        return response()->json(['success' => true, 'message' => 'Topic Successfully Created', 'topic' => $topic]);
    }


    public function export(Request $request)
    {
        $languageId = $request->get('language_id');
        $categoryId = $request->get('category_id');
        $subCategoryId = $request->get('sub_category_id');
        $subjectId = $request->get('subject_id');

        return Excel::download(new topicsExport($languageId, $categoryId, $subCategoryId,  $subjectId), 'Topic.xlsx');
    }


    public function sample()
    {
        return Excel::download(new SampletopicsExport, 'SampleTopic.xlsx');
    }



    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx',
        ]);

        $importer = new topicsImport();

        try {
            $rows = Excel::import($importer, $request->file('file'));

            foreach ($rows as $key => $row) {
                $rowCount = $key + 2;

                if (empty($row['subject_id'])) {
                    return back()->with('error', 'Row: ' . $rowCount . '- Subcategory is required.');
                }

                if (!$this->getTopicId($row['subject_id'])) {
                    return back()->with('error', 'Subcategory - "' . $row['subject_id'] . '" not available');
                }
            }
        } catch (\Exception $e) {
            return redirect()->route('topic.index')
                ->with('import_errors', [$e->getMessage()]);
        }

        if (!empty($importer->errors)) {
            return redirect()->route('topic.index')
                ->with('import_errors', $importer->errors);
        }

        return redirect()->route('topic.index')->with('success', 'topics imported successfully!');
    }

    private function getTopicId($id)
    {
        return \App\Models\Topic::find($id)->id ?? null;
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
        $topic = Topic::with('subject')->find($id);

        $subjects = Subject::all();

        $languages = Language::all();

        return view('topics.edit', compact('topic', 'subjects', 'languages'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        request()->validate([
            'name' => 'required',
            'subject_id' => 'required'
        ]);

        $topic =  Topic::find($id);

        $topic->update(request()->all());

        // if ($request->hasFile('photo')) {
        //     $fileName = "topic/" . time() . "_photo.jpg";

        //     $request->file('photo')->storePubliclyAs('public', $fileName);

        //     $topic->photo = $fileName;

        //     $topic->save();
        // }

        if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
            $fileName = "topic/" . time() . "_photo.jpg";   // same as Laravel path format
            $storagePath = "storage/" . $fileName;             // simulating Laravel storage/public disk

            // Create folder if not exists
            if (!is_dir("storage/topic")) {
                mkdir("storage/topic", 0777, true);
            }

            if (move_uploaded_file($_FILES['photo']['tmp_name'], $storagePath)) {
                $topic->photo = $fileName;
                $topic->save();
            }
        }

        return response()->json(['success' => true, 'message' => 'Topic Successfully Updated', 'topic' => $topic]);

        // session()->flash('success', 'Topic Successfully Updated');

        // return redirect()->route('topic.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Topic::destroy($id);

        session()->flash('success', 'Topic Successfully Deleted');

        return redirect()->route('topic.index');
    }
}
