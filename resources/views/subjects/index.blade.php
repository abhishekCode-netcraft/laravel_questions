@extends('layouts.app')

@section('title', 'Subject')

@section('content')

<div class="flex justify-between">
    <h1 class="mb-4 text-2xl font-extrabold leading-none tracking-tight text-gray-900 md:text-5xl lg:text-2xl dark:text-white">Subject</h1>
    <div class="flex justify-end items-center gap-2">
        <a href="{{ route('subject.export',request()->query()) }}" class="text-center hover:text-white border border-bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
            Export
        </a>

        <button id="importButton" class="text-center hover:text-white border border-bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">Import</button>
        <input type="file" id="importInput" name="file" class="form-control hidden" required>

        <a href="{{ route('subject.sample') }}" class="text-center hover:text-white border border-bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
            Download Sample
        </a>

        <button id="createButton" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
            Create
        </button>
    </div>

</div>

<div class="relative overflow-x-auto shadow-md sm:rounded-lg space-y-5">
    <form action="{{ route('subject.index') }}" method="GET">
        <div class="flex gap-x-5">
            @foreach ($dropdown_list as $moduleName => $module)
            @php
            $id = strtolower(Str::slug($moduleName, '_'));
            $moduleKey = Str::slug(strtolower(trim(explode('Select', $moduleName)[1])) . "_id", '_');
            $selectedValue = request()->input($moduleKey);
            @endphp
            <div>
                <input type="hidden" value="{{ $module[$moduleKey] ?? '' }}" name="{{ $moduleKey }}" />
                <select name="{{ $moduleKey }}" class="{{ $id }} bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500 required-field">
                    <option value="">{{$moduleName}}</option>
                    @foreach($module as $item)
                    <option value="{{$item->id}}" {{ $selectedValue == $item->id ?  'selected' : '' }}>{{$item->name}}</option>
                    @endforeach
                </select>
                <div class="text-red-500 text-xs mt-1 validation-msg"></div> <!-- Validation Message -->
                @error('module.' . $moduleKey)
                <div class="text-red-500 text-xs mt-1">{{ $message }}</div>
                @enderror
            </div>
            @endforeach
            <button type="submit" class="text-white text-center bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 flex items-center me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">Filter</button>
        </div>
    </form>
    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
            <tr>
                <th scope="col" class="px-6 py-3">
                    <a href="{{ route('subject.index', array_merge(request()->all(), ['sort' => 'id', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}">
                        #
                        @if ($sortColumn == 'id')
                        @if ($sortDirection == 'asc')
                        ▲
                        @else
                        ▼
                        @endif
                        @endif
                    </a>
                </th>
                <th scope="col" class="px-6 py-3">
                    <a href="{{ route('subject.index', array_merge(request()->all(), ['sort' => 'language', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}">
                        Language Name
                        @if ($sortColumn == 'language')
                        @if ($sortDirection == 'asc')
                        ▲
                        @else
                        ▼
                        @endif
                        @endif
                    </a>
                </th>
                <th scope="col" class="px-6 py-3">
                    <a href="{{ route('subject.index', array_merge(request()->all(), ['sort' => 'category', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}">
                        Category Name
                        @if ($sortColumn == 'category')
                        @if ($sortDirection == 'asc')
                        ▲
                        @else
                        ▼
                        @endif
                        @endif
                    </a>
                </th>
                <th scope="col" class="px-6 py-3">
                    <a href="{{ route('subject.index', array_merge(request()->all(), ['sort' => 'sub_category', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}">
                        Sub Category Name
                        @if ($sortColumn == 'sub_category')
                        @if ($sortDirection == 'asc')
                        ▲
                        @else
                        ▼
                        @endif
                        @endif
                    </a>
                </th>
                <th scope="col" class="px-6 py-3">
                    Images
                </th>
                <th scope="col" class="px-6 py-3">
                    <a href="{{ route('subject.index', array_merge(request()->all(), ['sort' => 'name', 'direction' => request('direction') === 'asc' ? 'desc' : 'asc'])) }}">
                        Subject Name
                        @if ($sortColumn == 'name')
                        @if ($sortDirection == 'asc')
                        ▲
                        @else
                        ▼
                        @endif
                        @endif
                    </a>
                </th>

                <th scope="col" class="px-6 py-3">
                    Question Count
                </th>

                <th scope="col" class="px-6 py-3">
                    Parent ID
                </th>
                <th scope="col" class="px-6 py-3">
                    Status
                </th>
                <th scope="col" class="px-6 py-3">
                    Action
                </th>
            </tr>
        </thead>
        <tbody>
            @forelse($subjects as $subject)
            <tr class="odd:bg-white odd:dark:bg-gray-900 even:bg-gray-50 even:dark:bg-gray-800 border-b dark:border-gray-700">
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->id}}
                </th>
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->subCategory->category->language->name}}
                </th>
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->subCategory->category->name}}
                </th>
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->subCategory->name}}
                </th>
                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    <img src="{{$subject->photo ? '/storage/'.$subject->photo : '/dummy.jpg'}}" style='width: 50px; height: 50px; border-radius: 50%; object-fit: cover; border:2px solid black;' />
                </th>

                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->name}}
                </th>

                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->question_count}}
                </th>

                <th scope="row" class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                    {{$subject->parent_id}}
                </th>

                @php
                $status = ucfirst($subject->status);
                $statusClass = $subject->status === 'enabled' ? 'text-green-600' : 'text-red-600';
                @endphp

                <th scope="row" class="px-6 py-4 font-medium whitespace-nowrap {{ $statusClass }}">
                    {{ $status }}
                </th>

                <td class="px-6 py-4 flex gap-4">
                    <button class="editButton font-medium text-blue-600 dark:text-blue-500 hover:underline"
                        data-id="{{ $subject->id }}"
                        data-name="{{ $subject->name }}"
                        data-language-id="{{ $subject->subCategory->category->language->id }}"
                        data-photo="{{ $subject->photo }}"
                        data-category-id="{{ $subject->subCategory->category->id }}"
                        data-parent-id="{{ $subject->parent_id }}"
                        data-status="{{ $subject->status }}"
                        data-plan="{{ $subject->plan }}"
                        data-amount="{{ $subject->amount }}"
                        data-validity="{{ $subject->validity }}"
                        data-sub-category-id="{{ $subject->subCategory->id }}">
                        <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 30 30">
                            <path d="M 22.828125 3 C 22.316375 3 21.804562 3.1954375 21.414062 3.5859375 L 19 6 L 24 11 L 26.414062 8.5859375 C 27.195062 7.8049375 27.195062 6.5388125 26.414062 5.7578125 L 24.242188 3.5859375 C 23.851688 3.1954375 23.339875 3 22.828125 3 z M 17 8 L 5.2597656 19.740234 C 5.2597656 19.740234 6.1775313 19.658 6.5195312 20 C 6.8615312 20.342 6.58 22.58 7 23 C 7.42 23.42 9.6438906 23.124359 9.9628906 23.443359 C 10.281891 23.762359 10.259766 24.740234 10.259766 24.740234 L 22 13 L 17 8 z M 4 23 L 3.0566406 25.671875 A 1 1 0 0 0 3 26 A 1 1 0 0 0 4 27 A 1 1 0 0 0 4.328125 26.943359 A 1 1 0 0 0 4.3378906 26.939453 L 4.3632812 26.931641 A 1 1 0 0 0 4.3691406 26.927734 L 7 26 L 5.5 24.5 L 4 23 z"></path>
                        </svg>
                    </button>

                    <form action="{{ route('subject.destroy', $subject->id) }}" method='POST'>
                        @csrf
                        @method('DELETE')
                        <button href="#" class="font-medium text-danger dark:text-danger-500 hover:underline" onclick="return confirm('Are you sure? Question - {{$subject->question_count}}')">
                            <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="20" height="20" viewBox="0 0 30 30">
                                <path d="M 14.984375 2.4863281 A 1.0001 1.0001 0 0 0 14 3.5 L 14 4 L 8.5 4 A 1.0001 1.0001 0 0 0 7.4863281 5 L 6 5 A 1.0001 1.0001 0 1 0 6 7 L 24 7 A 1.0001 1.0001 0 1 0 24 5 L 22.513672 5 A 1.0001 1.0001 0 0 0 21.5 4 L 16 4 L 16 3.5 A 1.0001 1.0001 0 0 0 14.984375 2.4863281 z M 6 9 L 7.7929688 24.234375 C 7.9109687 25.241375 8.7633438 26 9.7773438 26 L 20.222656 26 C 21.236656 26 22.088031 25.241375 22.207031 24.234375 L 24 9 L 6 9 z"></path>
                            </svg>
                        </button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" align="center">No Result Found</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if(request()->data != 'all')
    <div class="flex justify-between items-center">
        <div style="width: 92%;">
            {{ $subjects->appends(request()->query())->links() }}
        </div>

        <div>
            <a href="{{ request()->fullUrlWithQuery(['data' => 'all']) }}"
                class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                View All
            </a>
        </div>
    </div>
    @endif
</div>

<div id="modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 50; background-color: rgba(0, 0, 0, 0.5);">
    <div style=" background-color: white; 
        border-radius: 10px; 
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
        width: 30%; 
        max-height: 90vh; /* Maximum height to keep it within the viewport */
        margin: auto; 
        padding: 24px; 
        position: relative; 
        overflow-y: auto; /* Make content scrollable if it exceeds max height */">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="modalTitle" style="font-size: 1.5rem; font-weight: bold;">Modal Title</h2>
            <button id="closeModal" style="background: none;border: 1px solid black;cursor: pointer;color: #6B7280;border-radius: 100%;width: 25px;">
                X
            </button>
        </div>
        <form id="modalForm" method="POST" action="" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="">

            <div class="relative" style="height: 100px;">
                <div class="container">
                    <input accept="image/*" type="file" class="opacity-0 w-[100] h-[100] absolute z-10 cursor-pointer" name="photo" style="width: 100px; height:100px;" id='fileInput' />
                    <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white image" src="/dummy.jpg" alt="" id='subjectImage' style='width:100px;height:100px;'>
                    <div class="bg-black/[0.5] overlay absolute h-[100%] top-[0px] w-[100px] rounded-full opacity-0 flex justify-center items-center text-white">Upload Pic</div>
                </div>
            </div>
            <div class="mb-5">
                <label for="name" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Name</label>
                <input type="text" id="name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" name="name" required />
                @error('name')
                <div class="text-danger">{{ $message }}</div>
                @enderror
            </div>
            <div class="mx-auto mb-5">
                <label for="countries" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Language</label>
                <select id="select_language" name="language_id" class="select_language bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">Select Language</option>
                    @foreach($languages as $item)
                    <option value="{{$item->id}}">{{$item->name}}</option>
                    @endforeach
                </select>
                @error('language_id')
                <div class="text-red-500">{{ $message }}</div>
                @enderror
            </div>

            <div class="mx-auto mb-5">
                <label for="countries" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Category</label>
                <select id='select_category' name="category_id" class="select_category bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">Choose a Category</option>
                    @foreach($categories as $category)
                    <option value="{{$category->id}}">{{$category->name}}</option>
                    @endforeach
                </select>
            </div>


            <div class="mx-auto mb-5">
                <label for="countries" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Sub Category</label>
                <select id='select_sub_category' name="sub_category_id" class="select_sub_category bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">Choose a Sub Category</option>
                    @foreach($subcategories as $sub_category)
                    <option value="{{$sub_category->id}}">{{$sub_category->name}}</option>
                    @endforeach
                </select>
                @error('sub_category_id')
                <div class="text-red-500">{{ $message }}</div>
                @enderror
            </div>
            

            <div class="form-group">
                <label for="status">Status</label>
                <select name="status" id="status" class="form-control">
                    <option value="enabled">Enabled</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>

            <div class="mx-auto mb-5">
                <label for="countries" class="block mb-2 text-sm font-medium text-gray-900 dark:text-white">Parent ID</label>
                <input type="number" name="parent_id" id='select_parent_id' class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" />
            </div>

            <button type="submit" style="background-color: #2563EB; color: white; font-size: 14px; font-weight: 500; border-radius: 8px; padding: 8px 16px; border: none; cursor: pointer;">
                Save
            </button>
        </form>
    </div>
</div>

@endsection

@push('scripts')

@include('script')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('createButton').addEventListener('click', function() {
            // Reset fields for creating a new subcategory
            document.getElementById('modalTitle').innerText = 'Create Subject';
            document.getElementById('modalForm').action = "{{ route('subject.store') }}";
            document.getElementById('modalForm').method = 'POST';
            document.getElementById('modalForm').querySelector('input[name="_method"]').value = '';
            document.getElementById('name').value = '';
            document.getElementById('select_language').value = '';
            document.getElementById('select_category').value = '';
            document.getElementById('select_sub_category').value = '';
            document.getElementById('subjectImage').src = '/dummy.jpg';
            document.getElementById('modal').style.display = 'flex';
        });

        const editButtons = document.querySelectorAll('.editButton');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                const languageId = this.getAttribute('data-language-id');
                const photo = this.getAttribute('data-photo');
                const categoryId = this.getAttribute('data-category-id');
                const subCategoryId = this.getAttribute('data-sub-category-id');
                const parentId = this.getAttribute('data-parent-id');
                const status = this.getAttribute('data-status');
                const plan = this.getAttribute('data-plan');
                const dataamount = this.getAttribute('data-amount');
                const validity = this.getAttribute('data-validity');
                // Set modal for editing a subcategory
                document.getElementById('modalTitle').innerText = 'Edit Subject';
                document.getElementById('modalForm').action = `/subject/${id}`;
                document.getElementById('modalForm').method = 'POST';
                document.getElementById('modalForm').querySelector('input[name="_method"]').value = 'PUT';
                document.getElementById('name').value = name;
                document.getElementById('select_language').value = languageId;
                document.getElementById('select_category').value = categoryId;
                document.getElementById('select_sub_category').value = subCategoryId;
                document.getElementById('select_parent_id').value = parentId;
                document.getElementById('status').value = status;
                //document.getElementById('plan').value = plan;
                //document.getElementById('amount').value = dataamount;
                //document.getElementById('validity').value = validity;
                document.getElementById('subjectImage').src = photo ? `/storage/${photo}` : '/dummy.jpg';
                document.getElementById('modal').style.display = 'flex';
            });
        });

        document.getElementById('fileInput').addEventListener('change', function(event) {
            var reader = new FileReader();
            reader.onload = function() {
                var output = document.getElementById('subjectImage');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        });

        $('#importButton').click(function() {
            //click in file select and save the file in hidden input
            $('#importInput').click();

            // when file is selected, create the form and submit 
            $('#importInput').change(function() {
                var form = $('<form>', {
                    'method': 'POST',
                    'action': '{{ route("subject.import") }}',
                    'enctype': 'multipart/form-data'
                });

                form.append($('<input>', {
                    'type': 'hidden',
                    'name': '_token',
                    'value': '{{ csrf_token() }}'
                }));

                form.append($(this));

                form.appendTo('body').submit();
            });
        });
    });
</script>

@endpush