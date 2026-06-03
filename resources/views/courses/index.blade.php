@extends('layouts.app')

@section('title', 'Course')

@section('content')

<div class="flex justify-between">
    <h1 class="mb-4 text-2xl font-extrabold leading-none tracking-tight text-gray-900 md:text-5xl lg:text-2xl dark:text-white">Course</h1>

    <div class="flex justify-end items-center gap-2">
        <button id="createButton" type="button" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 me-2 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">
            Create
        </button>
        <input type="text" id="searchFilter" placeholder="Search Offers..." class="border border-gray-300 rounded-lg text-sm px-4 py-2 dark:bg-gray-700 dark:text-white">
    </div>
</div>

<x-dynamic-table :columns="$courseTableHeader" :rowData="$courses" :rows="$courseTableRow" />

<div id="modal" style="display: none; position: fixed; inset: 0; align-items: center; justify-content: center; z-index: 50; background-color: rgba(0, 0, 0, 0.5);">
    <div style="
        background-color: white; 
        border-radius: 10px; 
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1); 
        width: 60%; 
        max-height: 90vh; /* Maximum height to keep it within the viewport */
        margin: auto; 
        padding: 24px; 
        position: relative; 
        overflow-y: auto;
    ">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 id="modalTitle" style="font-size: 1.5rem; font-weight: bold;">Modal Title</h2>
            <button id="closeModal" style="background: none;border: 1px solid black;cursor: pointer;color: #6B7280;border-radius: 100%;width: 25px;">X</button>
        </div>

        <form id="modalForm" method="POST" action="" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_method" value="">

            <div class="mb-3 relative" style="height: 100px;">
                <div class="container">
                    <input accept="image/*" type="file" class="opacity-0 w-[100] h-[100] absolute z-10 cursor-pointer" name="banner" style="width: 100px; height:100px;" id='fileInput' />
                    <img class="inline-block h-8 w-8 rounded-full ring-2 ring-white image" src="/dummy.jpg" alt="" id='offerImage' style='width:100px;height:100px;'>
                    <div class="bg-black/[0.5] overlay absolute h-[100%] top-[0px] w-[100px] rounded-full opacity-0 flex justify-center items-center text-white">Upload Pic</div>
                </div>
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Course Name</label>
                <input type="text" id="name" placeholder="Course Name" class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500" name="name"  />
                <div style="color: red;" id="error-name"></div>  
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Meta Data</label>
                <textarea id='meta_data' name='meta_data' class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">

                </textarea>
            </div>

            <div class="mx-auto mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Languages</label>
                <select id="select_language" name="language_id" class="select_language bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value="">Select Language</option>
                    @foreach($languages as $item)
                    <option value="{{$item->id}}">{{$item->name}}</option>
                    @endforeach
                </select>
                <div id="error-language_id" class="error-text" style="color: red;"></div>
            </div>

            <div class="mx-auto mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                <select id='select_category' name="category_id" class="select_category bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    {{-- <option value="">Choose a Category</option> --}}
                    
                </select>
                <div id="error-category_id" class="error-text" style="color: red;"></div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Sub Categories</label>
                <div class="mb-3 relative">
                    <select id='select_sub_category' multiple name="subcategories[]" class="select_sub_category bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        {{-- <option value="">Choose a Sub Category</option> --}}
                    </select>
                    {{-- <button type="button" onclick="toggleDropdown()" class=" w-full border border-gray-300 rounded-md px-4 py-2 text-left bg-white">
                        <span id="subcategoryButtonLabel">Select Sub Categories</span>
                    </button> --}}

                    <!-- <div id="dropdownMenu" style="max-height: 200px;" class="absolute z-10 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg hidden max-h-60 overflow-auto">
                        <label class="flex items-center px-4 py-2">
                            <input type="checkbox" onchange="toggleSelectAll(this)" />
                            <span class="ml-2">Select All</span>
                        </label>
                        @foreach($subcategories as $sub)
                            <label class="flex items-center px-4 py-2">
                                <input type="checkbox" name="subcategories[]" value="{{ $sub->id }}" class="subcategory-checkbox" data-name="{{ $sub->name }}" />
                                <span class="ml-2">{{ $sub->name }}</span>
                            </label>
                        @endforeach
                    </div> -->
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subjects</label>
                <div class="mb-3 relative">
                    <select id='select_subject' multiple name="subjects[]" class="select_subject bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        <option value="">Choose a Subjects</option>
                    </select>
                </div>
            </div>
          
          	<div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Topics</label>
                <div class="mb-3 relative">
                    <select id='select_topic' multiple name="topics[]" class="select_topic bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                        <option value="">Choose a Topics</option>
                    </select>
                </div>
            </div>

            <div class="mx-auto mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Paid/Free</label>
                <select id="paid_free" name="is_paid" class="paid_free bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 dark:text-white dark:focus:ring-blue-500 dark:focus:border-blue-500">
                    <option value=0>Free</option>
                    <option value=1>Paid</option>
                </select>
            </div>

            <div class="mb-4 subscription_section">
                <label class="block text-sm font-medium text-gray-700 mb-2">Subscription Plans</label>

                <!-- Monthly -->
                <div class="flex items-center gap-2 mb-2">
                    <input type="checkbox" id="monthlyCheck" name="subscription[monthly][active]" value="1" class="subscriptionCheck" />
                    <label for="monthlyCheck" class="w-24">Monthly</label>
                    <input type="number" name="subscription[monthly][amount]" placeholder="Amount" class="border border-gray-300 rounded p-1 w-28" />
                    <input type="number" name="subscription[monthly][validity]" value="30" max="30" placeholder="Validity " class="border border-gray-300 rounded p-1 w-36" />
                </div>

                <!-- Semi Annual -->
                <div class="flex items-center gap-2 mb-2">
                    <input type="checkbox" id="semiAnnualCheck" name="subscription[semi_annual][active]" value="1" class="subscriptionCheck" />
                    <label for="semiAnnualCheck" class="w-24">Semi Annual</label>
                    <input type="number" name="subscription[semi_annual][amount]" placeholder="Amount" class="border border-gray-300 rounded p-1 w-28" />
                    <input type="number" name="subscription[semi_annual][validity]"  value="180" max="180" placeholder="Validity " class="border border-gray-300 rounded p-1 w-36" />
                </div>

                <!-- Annual -->
                <div class="flex items-center gap-2">
                    <input type="checkbox" id="annualCheck" name="subscription[annual][active]" value="1" class="subscriptionCheck" />
                    <label for="annualCheck" class="w-24">Annual</label>
                    <input type="number" name="subscription[annual][amount]" placeholder="Amount" class="border border-gray-300 rounded p-1 w-28" />
                    <input type="number" name="subscription[annual][validity]" value="365" max="365" placeholder="Validity " class="border border-gray-300 rounded p-1 w-36" />
                </div>
            </div>

            <div class="flex flex-row items-center gap-4 mb-4">
                <div class="flex gap-2">
                    <!-- <input type="radio" class="form-control" name="single_language"> -->
                    <input type="radio" name="language" id="single" value=0> Single Language
                </div>

                <div class="flex gap-2">
                    <!-- <input type="radio" class="form-control" name="multi_language"> -->
                    <input type="radio" name="language" id="multiple" value=1> Multi Language
                </div>
            </div>

            <div class="flex gap-4 mb-4">
                <label>
                    <input type='radio' name='part' id='subject_wise' value='subject' />
                    Subject Wise
                </label>
                <label>
                    <input type='radio' name='part' id='part_wise' value='part' />
                    Part Wise
                </label>
            </div>

            <div class='d-flex' style='grid-gap:10px;'>
                <div class="accordion" id="subjectAccordion" style='width: 100%;display: flex;flex-direction: column;grid-gap: 10px;'></div>
            
                <table class='table' id='part' style='display:none;'>
                    
                </table>
            </div>
          
            <div class="mb-3">
                <select id="status" name="status" class="mt-4 bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <option value="">Select Status</option>
                    <option value="1">Enabled</option>
                    <option value="0">Disabled</option>
                </select>
                <div id="error-status" class="error-text" style="color: red;"></div>
            </div>

            <div class="mb-3">
                <label class="block text-sm font-medium text-gray-700 mb-1">Subscription Tier</label>
                <select id="tier" name="tier" required class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <option value="basic">Basic</option>
                    <option value="standard">Standard</option>
                    <option value="premium">Premium</option>
                </select>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Course Features</label>
                <select id="select_features" multiple name="features[]" class="select_features multi-select bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-lg focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5">
                    <option value="all">All Features</option>
                    <option value="Videos">Videos</option>
                     <option value="Live Test">Live Test</option>
                    <option value="CBT Mock test">CBT Mock test</option>
                    <option value="Practice quiz">Practice quiz</option>
                    <option value="Digital Notes">Digital Notes</option>
                    <option value="Question bank">Question bank</option>
                   
                </select>
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
    $(document).ready(function() {
        $('#select_sub_category').select2({
            width: '100%'
        });
      
        $('#select_subject').select2({
            width: '100%'
        });

        $('#select_features').select2({
            width: '100%',
            placeholder: 'Select Features'
        });
      
	    $('#select_topic').select2({
            width: '100%'
        });

        function handleSelectAll(selector) {
            $(selector).on('select2:select', function (e) {
                if (e.params.data.id === "all") {
                    let allValues = [];

                    $(selector + ' option').each(function () {
                        if ($(this).val() !== "all") {
                            allValues.push($(this).val());
                        }
                    });

                    $(selector).val(allValues).trigger('change');
                }
            });
        }

        // Apply to all selects
        handleSelectAll('#select_subject');
        handleSelectAll('#select_sub_category');
        handleSelectAll('#select_topic');
        handleSelectAll('#select_features');

        $('#paid_free').change(function(){
            if ($(this).val() == '0') {
                $('.subscription_section').show();
            } else {
                $('.subscription_section').hide();
            }
        });
    });

    function updateSubCategoryLabel() {
        const checked = document.querySelectorAll('.subcategory-checkbox:checked');
        const labelSpan = document.getElementById('subcategoryButtonLabel');
        
        if (checked.length === 0) {
            labelSpan.textContent = 'Select Sub Categories';
            return;
        }

        const names = Array.from(checked).map(cb => cb.dataset.name);
        labelSpan.textContent = names.join(', ');
    }

    // Add change listeners to subcategory checkboxes
    document.querySelectorAll('.subcategory-checkbox').forEach(cb => {
        cb.addEventListener('change', () => {
            updateSubCategoryLabel();
            fetchSubjects(); // Optional: if you still want to fetch subjects on change
        });
    });

    // Update label when dropdown is closed (click outside)
    // document.addEventListener('click', function(event) {
    //     const dropdown = document.getElementById('dropdownMenu');
    //     const button = event.target.closest('button');

    //     if (!dropdown.contains(event.target) && !button) {
    //         dropdown.classList.add('hidden');
    //         updateSubCategoryLabel(); // 👈 Ensure label is updated when dropdown is closed
    //     }
    // });

    function toggleSubjectDropdown() {
        document.getElementById('subjectDropdownMenu').classList.toggle('hidden');
    }

    // Close dropdown on outside click
    // document.addEventListener('click', function (event) {
    //     const dropdown = document.getElementById('subjectDropdownMenu');
    //     const button = event.target.closest('button');
    //     if (!dropdown.contains(event.target) && !button) {
    //         dropdown.classList.add('hidden');
    //     }
    // });

    function toggleDropdown() {
        const dropdown = document.getElementById('dropdownMenu');
        dropdown.classList.toggle('hidden');
    }

    // Close dropdown on outside click
    // document.addEventListener('click', function(event) {
    //     const dropdown = document.getElementById('dropdownMenu');
    //     const button = event.target.closest('button');
    //     if (!dropdown.contains(event.target) && !button) {
    //         dropdown.classList.add('hidden');
    //     }
    // });

    function toggleSelectAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.subcategory-checkbox');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
    }

    document.getElementById('searchFilter').addEventListener('input', function () {
        let filter = this.value.toLowerCase();
        let rows = document.querySelectorAll('#offersTable .offerRow');
        
        rows.forEach(row => {
            let text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        });
    });

    document.getElementById('fileInput').addEventListener('change', function(event) {
        var reader = new FileReader();
        reader.onload = function() {
            var output = document.getElementById('offerImage');
            output.src = reader.result;
        };
        reader.readAsDataURL(event.target.files[0]);
    });

    document.addEventListener('DOMContentLoaded', function() {
        document.getElementById('createButton').addEventListener('click', function() {
            document.getElementById('modalTitle').innerText = 'Create Course';
            document.getElementById('modalForm').action = "{{ route('courses.store') }}";
            document.getElementById('modalForm').method = 'POST';
            document.getElementById('modalForm').querySelector('input[name="_method"]').value = '';
            document.getElementById('name').value = '';
            document.getElementById('select_language').value = '';
            document.getElementById('select_category').value = '';
            document.getElementById('status').selectedIndex = "";
            document.getElementById('stars').value = '0';
            $('#select_features').val(null).trigger('change');

            // Reset checkboxes for subcategories
            document.querySelectorAll('.subcategory-checkbox').forEach(cb => cb.checked = false);
            // document.querySelector('#subcategoryButtonLabel').textContent = 'Select Sub Categories';

            // Reset checkboxes for subjects
            document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = false);
            // document.querySelector('#selectedSubjectsText').textContent = 'Select Subjects';

            // Reset subscription checkboxes and inputs
            document.querySelectorAll('.subscriptionCheck').forEach(cb => cb.checked = false);

            // Clear error messages
            document.querySelectorAll('.error-text').forEach(el => el.textContent = '');
            document.getElementById('offerImage').src = '/dummy.jpg';
            document.getElementById('modal').style.display = 'flex';
        });

        // $('#select_sub_category').prepend('<option value="all">Select All</option>');
        // $('#select_subject').prepend('<option value="all">Select All</option>');

        $(document).on('click', '.editButton', function () {
            var data = JSON.parse($(this).attr('data'));

            const id = data.id;
            const name = data.name;
            const meta_data = data.meta_data;
            const languageId = data.language_id;
            const categoryId = data.category_id;
            const subcategories = (data.sub_category_id);
            const subjects = (data.subject_id);
			const topics = (data.topic_id);
            const status = data.status;
            const banner = data.banner;
            const language = data.language;
            // const question_limit = data.question_limit;
            const subscriptions = data.subscription;
            const stars = data.stars;
            const tier = data.tier;
            const features = data.features;
            const isPaid = data.is_paid;
            const partWise = data.part_limit;
            const subjectWise = data.subject_limit;
            const subjectName = data.subject_names.split(",");

            $('#subjectAccordion').empty();
            $('#part').empty();

            setTimeout(() => {
                $("#select_sub_category").val(subcategories).trigger('change');
            }, 1000);

            setTimeout(() => {
                $("#select_subject").val(subjects).trigger('change');
                // $('#select_subject').prepend('<option value="all">Select All</option>');
                // $('#select_sub_category').prepend('<option value="all">Select All</option>');

                //const subjects = $(this).val();
                let groupedData = {};

                let requests = subjects.map(subjectId => {
                    return $.ajax({
                        url: '/get-subcategories-from-subject/' + subjectId,
                        method: 'GET'
                    }).then(data => {
                        const subCategoryId = data[1].id;    // assuming backend gives subcategory id
                        const subCategoryName = data[1].name;
                        const subject = { id: subjectId, name: data[0].name };

                        if (!groupedData[subCategoryId]) {
                            groupedData[subCategoryId] = {
                                name: subCategoryName,
                                subjects: []
                            };
                        }

                        groupedData[subCategoryId].subjects.push(subject);
                    });
                });

                getSubjectWise(groupedData, requests);

                getPartWise(groupedData, requests);
            }, 2000);
          
          setTimeout(() => {
                $("#select_topic").val(topics).trigger('change');
                // $('#select_topic').prepend('<option value="all">Select All</option>');
            }, 3000);

            function getSubjectWise(groupedData, requests)
            {
                Promise.all(requests).then(() => {
                    Object.entries(groupedData).forEach(([subCategoryId, group]) => {
                        const collapseId = "collapse-" + subCategoryId;

                        let rows = group.subjects.map(subject => `
                            <tr>
                                <td class="px-3 py-2 border">${group.name}</td>
                                <td class="px-3 py-2 border">${subject.name}</td>
                                <td class="px-3 py-2 border">
                                    <input type="text" name="subject_limit[${subject.id}]" 
                                        data-subject="${subject.id}" 
                                        class="w-24 border rounded px-2 py-1" />
                                </td>
                                <td class="px-3 py-2 border">
                                    <input type="number" name="subject_limit[position][${subject.id}]" 
                                        data-position="${subject.id}" 
                                        class="w-24 border rounded px-2 py-1" />
                                </td>
                            </tr>
                        `).join('');

                        let accordionItem = `
                            <div class="border rounded-lg">
                                <button type="button" 
                                        class="w-full flex justify-between items-center px-4 py-2 text-left font-medium bg-gray-100 hover:bg-gray-200 rounded-t-lg accordion-toggle"
                                        data-target="#${collapseId}">
                                    <span>${group.name}</span>
                                    <svg class="w-5 h-5 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div id="${collapseId}" class="hidden px-4 pb-4 pt-2 sub-category-section" data-subcategory="${subCategoryId}">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full border text-sm text-left" style="width: -webkit-fill-available;">
                                            <thead class="bg-gray-200">
                                                <tr>
                                                    <th class="px-3 py-2 border">Sub Category</th>
                                                    <th class="px-3 py-2 border">Subject Name</th>
                                                    <th class="px-3 py-2 border">Limit</th>
                                                    <th class="px-3 py-2 border">Position</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${rows}
                                            </tbody>
                                        </table>
                                    </div>
									<div class='text-center mt-2'>
                                        <label>
                                            Question Marks:
                                            <input type="text" name="subject_limit[question_marks][${subCategoryId}]" id="${collapseId}-question_marks" class='w-24 border rounded px-2 py-1' />    
                                        </label>
                                        <label>
                                            Negative Marks:
                                            <input type="text" name="subject_limit[negative_marks][${subCategoryId}]" id="${collapseId}-negative_marks" class='w-24 border rounded px-2 py-1' />    
                                        </label>
                                    </div>
                                </div>
                            </div>
                        `;

                        $("#subjectAccordion").append(accordionItem);
                      
	                    //const subCategorySection = document.querySelector(".sub-category-section");
                        //accordianCollapseId = subCategorySection.id;
                      	//const subCategoryId = subCategorySection.dataset.subcategory;collapse-3-question_marks
                      	negativeInput = document.querySelector("#collapse-"+subCategoryId + '-negative_marks');
	                    questionInput = document.querySelector("#collapse-"+subCategoryId + '-question_marks');
                      
                      	console.log(negativeInput);
                      
                      	if (negativeInput) negativeInput.value = subjectWise.negative_marks[subCategoryId] ?? '';
                      	if (questionInput) questionInput.value = subjectWise.question_marks[subCategoryId] ?? '';
                        
                    });

                    if (typeof subjectWise !== 'undefined' && subjectWise) {
                        document.querySelector('#subject_wise').checked = true;
                        document.getElementById('part').style.display = 'none';
                        document.getElementById('subjectAccordion').style.display = 'block';

                        Object.entries(subjectWise).forEach(([sId, value]) => {
                            const input = document.querySelector(`input[data-subject="${sId}"]`);
                            const positionInput = document.querySelector(`input[data-position="${sId}"]`);

                          	if (input) input.value = value ?? '';
                            if (positionInput) positionInput.value = subjectWise.position[sId] ?? '';
                        });
                    }
                });
            }

            function getPartWise(groupedData, requests)
            {
                Promise.all(requests).then(() => {
                    Object.entries(groupedData).forEach(([subCategoryId, group]) => {
                        const collapseId = "collapse-" + subCategoryId;

                        // Create rows (based on your old structure)
                        let rows = group.subjects.map(subject => `
                            <tr>
                                <td class="px-3 py-2 border">${group.name}</td>
                                <td class="px-3 py-2 border">${subject.name}</td>

                                <!-- Limit field 1 -->
                                <td class="px-3 py-2 border">
                                    <input 
                                        type="text" 
                                        name="part_limit[limit][${subject.id}][]"  
                                        data-subject="${subject.id}" 
                                        data-index="0"
                                        class="w-24 border rounded px-2 py-1"
                                    />
                                </td>

                                <!-- Empty td (if you want to keep spacing consistent) -->
                                <td class="px-3 py-2 border"></td>

                                <!-- Limit field 2 -->
                                <td class="px-3 py-2 border">
                                    <input 
                                        type="text" 
                                        name="part_limit[limit][${subject.id}][]"  
                                        data-subject="${subject.id}" 
                                        data-index="1"
                                        class="w-24 border rounded px-2 py-1"
                                    />
                                </td>

                                <!-- Position field -->
                                <td class="px-3 py-2 border">
                                    <input 
                                        type="number" 
                                        name="part_limit[position][${subject.id}]" 
                                        data-position="${subject.id}"
                                        data-index="1"
                                        class="w-24 border rounded px-2 py-1"
                                    />
                                </td>
                            </tr>
                        `).join('');

                        // Create accordion section
                        let accordionItem = `
                            <div class="border rounded-lg mb-2">
                                <button type="button" 
                                        class="w-full flex justify-between items-center px-4 py-2 text-left font-medium bg-gray-100 hover:bg-gray-200 rounded-t-lg accordion-toggle"
                                        data-target="#${collapseId}_part">
                                    <span>${group.name}</span>
                                    <svg class="w-5 h-5 transition-transform transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div id="${collapseId}_part" class="hidden px-4 pb-4 pt-2">
                                    <div class="overflow-x-auto">
                                        <table class="min-w-full border text-sm text-left" style="width: -webkit-fill-available;">
                                            <thead class="bg-gray-200">
                                                <tr>
                                                    <th class="px-3 py-2 border">Sub Category</th>
                                                    <th class="px-3 py-2 border">Subject Name</th>
                                                    <th class="px-3 py-2 border">Limit 1</th>
                                                    <th class="px-3 py-2 border"></th>
                                                    <th class="px-3 py-2 border">Limit 2</th>
                                                    <th class="px-3 py-2 border">Position</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                ${rows}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        `;

                        $("#part").append(accordionItem);
                    });
                    
                    if (typeof partWise !== 'undefined' && partWise) {
                        document.querySelector('#part_wise').checked = true;
                        document.getElementById('part').style.display = 'table';
                        document.getElementById('subjectAccordion').style.display = 'none';
    
                        Object.entries(partWise.limit).forEach(([subjectId, values]) => {
                            values.forEach((value, index) => {
                                const limitInput = document.querySelector(`input[data-subject="${subjectId}"][data-index="${index}"]`);
                                const positionInput = document.querySelector(`input[data-position="${subjectId}"][data-index="${index}"]`);
    
                                if (limitInput) {
                                    limitInput.value = value ?? '';
                                }
    
                                if (positionInput) {
                                    positionInput.value = partWise.position[subjectId] ?? '';
                                }
                            });
                        });
                    }
                });
            }

            // $('#select_subject').off('change').on('change', function() {
            //     $("#subjectAccordion").empty();
            // });

            // Tailwind accordion toggle
            $(document).on("click", ".accordion-toggle", function() {
                const target = $($(this).data("target"));
                const icon = $(this).find("svg");

                if (target.hasClass("hidden")) {
                    target.removeClass("hidden");
                    icon.addClass("rotate-180");
                } else {
                    target.addClass("hidden");
                    icon.removeClass("rotate-180");
                }
            });

            // Fill modal fields
            document.getElementById('modalTitle').innerText = 'Edit Course';
            document.getElementById('modalForm').action = `/courses/${id}`;
            // document.querySelector('input[name="_method"]').value = 'PUT';
            document.getElementById('modalForm').method = 'POST';
            document.getElementById('modalForm').querySelector('input[name="_method"]').value = 'PUT';

            document.getElementById('name').value = name || '';
            document.getElementById('meta_data').innerHTML = meta_data || '';
            document.getElementById('select_language').value = languageId || '';
            document.getElementById('select_category').value = categoryId || '';
            // document.getElementById('stars').value = stars || 0;
            document.getElementById('tier').value = tier || 0;
            document.getElementById('paid_free').value = isPaid || 0;
            $('#select_features').val(features).trigger('change');

            const subcategoryCheckboxes = document.querySelectorAll('.subcategory-checkbox');
            subcategoryCheckboxes.forEach(checkbox => {
                const val = checkbox.value;
                checkbox.checked = subcategories.includes(val) || subcategories.includes(parseInt(val));
            });

            // OPTIONAL: Update dropdown label to show selected names
            const checkedSubCats = Array.from(subcategoryCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.getAttribute('data-name'));

            // Set subjects (checkbox-style dropdown)
            const subjectCheckboxes = document.querySelectorAll('.subject-checkbox');
            subjectCheckboxes.forEach(checkbox => {
                const val = checkbox.value;
                checkbox.checked = subjects.includes(val) || subjects.includes(parseInt(val));
            });

            // Optional: Update dropdown button label
            const checkedSubjects = Array.from(subjectCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.getAttribute('data-name'));

            // document.getElementById('selectedSubjectsText').innerText = checkedSubjects.length
            //     ? checkedSubjects.join(', ')
            //     : 'Select Subjects';

            // Monthly
            document.getElementById('monthlyCheck').checked = !!subscriptions.monthly;
            document.querySelector('input[name="subscription[monthly][amount]"]').value = subscriptions.monthly?.amount || '';
            document.querySelector('input[name="subscription[monthly][validity]"]').value = subscriptions.monthly?.validity || '';

            // Semi Annual
            document.getElementById('semiAnnualCheck').checked = !!subscriptions.semi_annual;
            document.querySelector('input[name="subscription[semi_annual][amount]"]').value = subscriptions.semi_annual?.amount || '';
            document.querySelector('input[name="subscription[semi_annual][validity]"]').value = subscriptions.semi_annual?.validity || '';

            // Annual
            document.getElementById('annualCheck').checked = !!subscriptions.annual;
            document.querySelector('input[name="subscription[annual][amount]"]').value = subscriptions.annual?.amount || '';
            document.querySelector('input[name="subscription[annual][validity]"]').value = subscriptions.annual?.validity || '';

            document.getElementById('status').value = status || '';
            document.getElementById('offerImage').src = banner ? `/uploads/courses/${banner}` : '/dummy.jpg';

            if (language == 0) {
                $('#single').attr('checked', 'checked');
            } else if (language == 1) {
                $('#multiple').attr('checked', 'checked');
            }

            // $('#question_limit').val(question_limit);

            // Show modal
            document.getElementById('modal').style.display = 'flex';
        });
    });

    $(document).on('input', 'input[data-subject]', function () {
        const subjectId = $(this).data('subject');
        const index = $(this).data('index');
        const otherIndex = index === 0 || index === "0" ? "1" : "0";

        const $otherInput = $(`input[data-subject="${subjectId}"][data-index="${otherIndex}"]`);

        if ($(this).val().trim() !== "") {
            $otherInput.val('');
            $otherInput.prop('readonly', true);
        } else {
            $otherInput.prop('readonly', false);
        }
    });

    function toggleDropdown() {
        document.getElementById('dropdownMenu').classList.toggle('hidden');
    }

    function toggleSelectAll(masterCheckbox) {
        const checkboxes = document.querySelectorAll('.subcategory-checkbox');
        checkboxes.forEach(cb => cb.checked = masterCheckbox.checked);
        fetchSubjects(); // Trigger fetch
    }

    // Fetch subject data based on selected subcategories
    function fetchSubjects() {
        const selected = Array.from(document.querySelectorAll('.subcategory-checkbox:checked'))
            .map(cb => cb.value);

        const container = document.getElementById('subjectDropdownMenu');
        container.innerHTML = ''; // clear previous

        if (selected.length > 0) {
            fetch(`/get-subjects?ids=${selected.join(',')}`)
                .then(res => res.json())
            .then(data => {
                if (data.length === 0) {
                    container.innerHTML = '<p class="text-gray-400 text-sm px-4 py-2">No subjects found</p>';
                    updateSelectedSubjectsDisplay();
                    return;
                }

                // Add "Select All" checkbox first
                const selectAllLabel = document.createElement('label');
                selectAllLabel.className = 'flex items-center px-4 py-2';

                const selectAllCheckbox = document.createElement('input');
                selectAllCheckbox.type = 'checkbox';
                selectAllCheckbox.id = 'selectAllSubjects';
                selectAllCheckbox.className = 'mr-2';

                const selectAllText = document.createElement('span');
                selectAllText.textContent = 'Select All';

                selectAllLabel.appendChild(selectAllCheckbox);
                selectAllLabel.appendChild(selectAllText);
                container.appendChild(selectAllLabel);

                // Add subject checkboxes
                data.forEach(subject => {
                    const label = document.createElement('label');
                    label.className = 'flex items-center px-4 py-2 hover:bg-gray-100';

                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'subjects[]';
                    checkbox.value = subject.id;
                    checkbox.className = 'mr-2 subject-checkbox';

                    const span = document.createElement('span');
                    span.textContent = subject.name;

                    label.appendChild(checkbox);
                    label.appendChild(span);
                    container.appendChild(label);
                });

                // Add event listeners
                setTimeout(() => {
                    document.querySelectorAll('.subject-checkbox').forEach(cb => {
                        cb.addEventListener('change', updateSelectedSubjectsDisplay);
                    });

                    // Select All toggle for subjects
                    document.getElementById('selectAllSubjects').addEventListener('change', function () {
                        const isChecked = this.checked;
                        document.querySelectorAll('.subject-checkbox').forEach(cb => cb.checked = isChecked);
                        updateSelectedSubjectsDisplay();
                    });
                }, 0);

                updateSelectedSubjectsDisplay();
            });
        } else {
            container.innerHTML = '<p class="text-gray-400 text-sm px-4 py-2">Select sub-categories to view subjects</p>';
            updateSelectedSubjectsDisplay(); // Clear display
        }
    }

    function updateSelectedSubjectsDisplay() {
        const selectedSubjects = Array.from(document.querySelectorAll('.subject-checkbox:checked'))
            .map(cb => cb.nextSibling.textContent.trim());

        const displaySpan = document.getElementById('selectedSubjectsText');

        if (selectedSubjects.length > 0) {
            displaySpan.textContent = selectedSubjects.join(', ');
        } else {
            displaySpan.textContent = 'Select Subjects';
        }
    }

    // Trigger fetch when any checkbox is clicked
    document.querySelectorAll('.subcategory-checkbox').forEach(cb => {
        cb.addEventListener('change', fetchSubjects);
    });

    document.addEventListener('DOMContentLoaded', function () {
        const validityLimits = {
            'monthly': 30,
            'semi_annual': 180,
            'annual': 365
        };

        Object.entries(validityLimits).forEach(([plan, maxVal]) => {
            const input = document.querySelector(`input[name="subscription[${plan}][validity]"]`);
            if (input) {
                input.addEventListener('input', function () {
                    if (parseInt(this.value) > maxVal) {
                        this.value = maxVal;
                    }
                });
            }
        });
    });
</script>

<script>
    $(document).ready(function(){
        $('#subject_wise').click(function() {
            $('#subjectAccordion').show();
            $('#part').hide();
        });

        $('#part_wise').click(function() {
            $('#subjectAccordion').hide();
            $('#part').show();
        });
    });
</script>

@endpush