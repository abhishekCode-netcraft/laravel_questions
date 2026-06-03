<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\UserCourse;
use App\Models\Course;

class UserCourseController extends Controller
{
    public function getUserCourses($userId)
    {
        $userCourses = UserCourse::where('user_id', $userId);

        if (request()->status) {
            $userCourses = $userCourses->where('status', request()->status);
        }

        $userCourses = $userCourses->get();

        $data = $userCourses->map(function ($userCourse) {
            $course = Course::find($userCourse->course_id);

            $metaData = json_decode($userCourse->meta_data, true);
            $provider = $metaData['provider'] ?? 'razorpay';
            $subscriptionSource = str_contains($provider, 'google_play') ? 'google_play' : 'razorpay';
            $autoRenewing = $metaData['auto_renewing'] ?? false;

            return [
                'user_id' => $userCourse->user_id,
                'course_id' => $userCourse->course_id,
                'plan_type' => $userCourse->subscription_type,
                'valid_from' => $userCourse->valid_from,
                'valid_to' => $userCourse->valid_to,
                'subscription_source' => $subscriptionSource,
                'auto_renewing' => (bool)$autoRenewing,
                'course_detail' => $course ? [
                    'id' => $course->id,
                    'name' => $course->name,
                    'meta_data' => $course->meta_data,
                    'features' => $course->features,
                    'language_id' => $course->language_id,
                    'category_id' => $course->category_id,
                    'sub_category_id' => $course->sub_category_id,
                    'subject_id' => $course->subject_id,
                    'status' => $course->status,
                    'subscription' => $course->subscription,
                    'banner' => $course->banner,
                ] : null,
            ];
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    public function assignCourseToUser(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required',
            'course_id' => 'required',
            'subscription_type' => 'required|string',
            'valid_from' => 'required|date',
            'valid_to' => 'required|date|after_or_equal:valid_from',
        ]);

        // Check if already assigned
        $alreadyAssigned = UserCourse::where('user_id', $validated['user_id'])
            ->where('course_id', $validated['course_id'])
            ->exists();

        if ($alreadyAssigned) {
            return response()->json([
                'status' => false,
                'message' => 'This course is already assigned to the user.'
            ], 409);
        }

        $userCourse = UserCourse::create($validated);

        return response()->json([
            'status' => true,
            'message' => 'Course assigned to user successfully.',
            'data' => $userCourse
        ]);
    }

    public function updateCourseStatus()
    {
        request()->validate([
            'user_id' => 'required|exists:google_users,id',
            'course_id' => 'required|exists:courses,id',
            'status' => 'required|in:1,0'
        ]);

        $userId = request()->user_id;
        $courseId = request()->course_id;
        $status = request()->status;

        if ($status) {
            UserCourse::where('user_id', $userId)
                ->update(['status' => 0]);
        }

        $userCourse = UserCourse::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->firstOrFail();

        $userCourse->update(['status' => $status]);

        return response()->json([
            'status' => true,
            'message' => 'Course status updated successfully.',
        ]);
    }
}