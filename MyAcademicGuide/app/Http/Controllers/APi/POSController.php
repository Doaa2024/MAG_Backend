<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;

class POSController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function pos(Request $request)
    {
        $user = $request->user()->userid;
        $courses = Student::where('studentid', $user)->with('department.courses.enrollments.timetable', 'department.courses.prerequisites', 'department.courses.prerequisiteFor')->first();
        $passedcourses = [];
        $registeredcourses = [];
        $remainingcourses = [];
        $canregister = [];
        $cannotregister = [];

        // Loop through the courses under the department
        foreach ($courses->department->courses as $course) {
            $coursestatus = Null;
            if ($course->enrollments && $course->enrollments->isNotEmpty()) { // Check if the course has enrollments
                foreach ($course->enrollments as $enrollment) {
                    $course->status = $enrollment->status;
                    if ($enrollment->status == 'Passed') {
                        $course->status = $enrollment->status;
                        $course->semestertaken = $enrollment->timetable->semester;
                        $course->yeartaken = $enrollment->timetable->year;
                        $passedcourses[] = $this->prepareCourseData($course); // Add the course to the passedcourses array
                        $coursestatus = 'Passed';
                        break; // Stop checking further enrollments for this course
                    } elseif ($enrollment->status == 'Registered') {
                        $coursestatus = 'Registered';
                        $registeredcourses[] = $this->prepareCourseData($course);
                        break;
                    }
                }
                if ($coursestatus == Null) {
                    $remainingcourses[] = $this->prepareCourseData($course);
                }
            } else {
                $remainingcourses[] = $this->prepareCourseData($course);
            }
        }

        foreach ($remainingcourses as $course) {
            if ($course['prerequisites']) { // Check if the course has prerequisites
                $prerequisites = $course['prerequisites']; // Fetch prerequisite courses
                $allPrerequisitesMet = true;

                foreach ($prerequisites as $prerequisite) {
                    $isInPassed = collect($passedcourses)->contains('courseid', $prerequisite['prerequisitecourseid']);
                    $isInRegistered = collect($registeredcourses)->contains('courseid', $prerequisite['prerequisitecourseid']);

                    // If a prerequisite is neither passed nor registered, mark course as cannot register
                    if (!$isInPassed && !$isInRegistered) {
                        $cannotregister[] = $course;
                        $allPrerequisitesMet = false;
                        break; // Stop checking further prerequisites for this course
                    }
                }

                if ($allPrerequisitesMet) {
                    $canregister[] = $course;
                }
            } else {
                $cannotregister[] = $course;
            }
        }
        $allCourses = $courses->department->courses->map(function ($course) use ($cannotregister, $canregister) {
            return $this->prepareAllCourseData($course, $canregister, $cannotregister);
        });


        return response()->json([
            'All Courses' => $allCourses,
            'Passed' => $passedcourses,
            'Currently Registered' => $registeredcourses,
            'Remaining Courses' => $remainingcourses,
            'Can Register' => $canregister,
            'Cannot Register' => $cannotregister,
        ]);
    }

    /**
     * Prepare course data for response
     */
    private function prepareAllCourseData($course, $canregister = [], $cannotregister = [])
    {
        $prerequisit = [];
        $postrequisit = [];

        foreach ($course->prerequisites as $prerequisiteId) {
            $prerequisit[] = $prerequisiteId;
        }
        foreach ($course->prerequisiteFor as $prerequisite) {
            $postrequisit[] = $prerequisite->courseid;
        }

        // Check if course exists in canregister or cannotregister and set status
        if (collect($canregister)->contains('courseid', $course->courseid)) {
            $status = 'Can Register';
        } elseif (collect($cannotregister)->contains('courseid', $course->courseid)) {
            $status = 'Cannot Register';
        } else {
            $status = $course->status; // Keep original status if not in lists
        }

        return [
            'courseid' => $course->courseid,
            'coursecode' => $course->coursecode,
            'coursename' => $course->coursename,
            'credits' => $course->credits,
            'semester' => $course->semester,
            'coursetype' => $course->coursetype,
            'status' => $status, // Updated status
            'prerequisites' => $prerequisit,
            'postrequisites' => $postrequisit,
        ];
    }


    private function prepareCourseData($course)
    {
        $prerequisit = [];
        $postrequisit = [];
        foreach ($course->prerequisites as $prerequisiteId) {
            $prerequisit[] = $prerequisiteId;
        }
        foreach ($course->prerequisiteFor as $prerequisite) {
            $postrequisit[] = $prerequisite->courseid;
        }
        if ($course->status == 'Passed') {
            return [
                'courseid' => $course->courseid,
                'coursecode' => $course->coursecode,
                'coursename' => $course->coursename,
                'credits' => $course->credits,
                'semester' => $course->semester,
                'coursetype' => $course->coursetype,
                'semestertaken' => $course->semestertaken,
                'yeartaken' => $course->yeartaken,
                'prerequisites' => $prerequisit,
                'postrequisites' => $postrequisit,

            ];
        } else {
            return [
                'courseid' => $course->courseid,
                'coursecode' => $course->coursecode,
                'coursename' => $course->coursename,
                'credits' => $course->credits,
                'semester' => $course->semester,
                'coursetype' => $course->coursetype,
                'prerequisites' => $prerequisit,
                'postrequisites' => $postrequisit,

            ];
        }
    }
}
