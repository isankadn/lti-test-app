<?php
/**
 * Demo Configuration File
 *
 * This file contains all configurable demo values that were previously hardcoded.
 * Modify these values to customize your LTI platform demo environment.
 */

// =============================================================================
// DOMAIN CONFIGURATION
// =============================================================================

// Tool Domains (External LTI Tools)
define('EXTERNAL_TOOL_BASE_DOMAIN', 'https://newleaf.let.media.kyoto-u.ac.jp');
define('ANALYSIS_TOOL_BASE_DOMAIN', EXTERNAL_TOOL_BASE_DOMAIN . '/analysis');
define('BOOKROLL_TOOL_BASE_DOMAIN', EXTERNAL_TOOL_BASE_DOMAIN . '/bookroll');
define('MOODLE_TOOL_BASE_DOMAIN', EXTERNAL_TOOL_BASE_DOMAIN . '/moodle');

// Platform Domain (Your Platform)
define('DEFAULT_PLATFORM_HOST', 'b19c-133-3-201-44.ngrok-free.app');
define('DEMO_EMAIL_DOMAIN', 'example.edu');

// =============================================================================
// COURSE CONFIGURATION
// =============================================================================

// Default Course Settings
define('DEMO_COURSE_PREFIX', 'course-demo');
define('DEMO_COURSE_CODE', 'DEMO-CS101');
define('DEMO_COURSE_TITLE', 'Introduction to Computer Science - Demo Course');
define('DEMO_COURSE_DESCRIPTION', 'LTI 1.3 Demo Course for Analysis Tool');

// Course Context Configuration
define('DEFAULT_CONTEXT_LABEL', 'CS101');
define('DEFAULT_CONTEXT_TITLE', 'Introduction to Computer Science');

// =============================================================================
// USER/MEMBER CONFIGURATION
// =============================================================================

// Demo Course Members
define('DEMO_MEMBERS', [
    // Instructor
    [
        'type' => 'instructor',
        'user_id' => 'instructor-001',
        'name' => 'Dr. Alice Johnson',
        'given_name' => 'Alice',
        'family_name' => 'Johnson',
        'middle_name' => '',
        'email' => 'alice.johnson@' . DEMO_EMAIL_DOMAIN,
        'lis_person_sourcedid' => 'prof001',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Instructor',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Instructor'
        ]
    ],

    // Students
    [
        'type' => 'student',
        'user_id' => 'student-001',
        'name' => 'John Smith',
        'given_name' => 'John',
        'family_name' => 'Smith',
        'middle_name' => '',
        'email' => 'john.smith@' . DEMO_EMAIL_DOMAIN,
        'lis_person_sourcedid' => 'student001',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student'
        ]
    ],

    [
        'type' => 'student',
        'user_id' => 'student-002',
        'name' => 'Jane Doe',
        'given_name' => 'Jane',
        'family_name' => 'Doe',
        'middle_name' => '',
        'email' => 'jane.doe@' . DEMO_EMAIL_DOMAIN,
        'lis_person_sourcedid' => 'student002',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student'
        ]
    ],

    [
        'type' => 'student',
        'user_id' => 'student-003',
        'name' => 'Bob Wilson',
        'given_name' => 'Bob',
        'family_name' => 'Wilson',
        'middle_name' => '',
        'email' => 'bob.wilson@' . DEMO_EMAIL_DOMAIN,
        'lis_person_sourcedid' => 'student003',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student'
        ]
    ],

    [
        'type' => 'student',
        'user_id' => 'student-004',
        'name' => 'Sarah Chen',
        'given_name' => 'Sarah',
        'family_name' => 'Chen',
        'middle_name' => '',
        'email' => 'sarah.chen@' . DEMO_EMAIL_DOMAIN,
        'lis_person_sourcedid' => 'student004',
        'roles' => [
            'http://purl.imsglobal.org/vocab/lis/v2/membership#Learner',
            'http://purl.imsglobal.org/vocab/lis/v2/institution/person#Student'
        ]
    ]
]);

// =============================================================================
// PLATFORM CONFIGURATION
// =============================================================================

// Platform Information
define('DEMO_PLATFORM_NAME', 'LTI 1.3 Demo Platform');
define('DEMO_PLATFORM_VERSION', '1.0.0');
define('DEMO_PLATFORM_PRODUCT_FAMILY', 'php-lti-platform');

// Launch Configuration
define('DEMO_LAUNCH_DOCUMENT_TARGET', 'iframe');
define('DEMO_LAUNCH_HEIGHT', 600);
define('DEMO_LAUNCH_WIDTH', 800);

// =============================================================================
// HELPER FUNCTIONS
// =============================================================================

/**
 * Get demo course information
 */
function getDemoCourseInfo($contextId = null) {
    if (!$contextId) {
        $contextId = DEMO_COURSE_PREFIX . '-' . date('Y-m-d');
    }

    return [
        'id' => $contextId,
        'label' => DEMO_COURSE_CODE,
        'title' => DEMO_COURSE_TITLE,
        'description' => DEMO_COURSE_DESCRIPTION,
        'type' => ['http://purl.imsglobal.org/vocab/lis/v2/course#CourseOffering']
    ];
}

/**
 * Get demo course members
 */
function getDemoCourseMembers() {
    $members = [];
    foreach (DEMO_MEMBERS as $memberConfig) {
        $members[] = [
            'status' => 'Active',
            'name' => $memberConfig['name'],
            'picture' => '',
            'given_name' => $memberConfig['given_name'],
            'family_name' => $memberConfig['family_name'],
            'middle_name' => $memberConfig['middle_name'],
            'email' => $memberConfig['email'],
            'user_id' => $memberConfig['user_id'],
            'lis_person_sourcedid' => $memberConfig['lis_person_sourcedid'],
            'roles' => $memberConfig['roles']
        ];
    }
    return $members;
}

/**
 * Get instructor from demo members
 */
function getDemoInstructor() {
    foreach (DEMO_MEMBERS as $member) {
        if ($member['type'] === 'instructor') {
            return $member;
        }
    }
    // Fallback to first member if no instructor found
    return DEMO_MEMBERS[0];
}

/**
 * Get students from demo members
 */
function getDemoStudents() {
    $students = [];
    foreach (DEMO_MEMBERS as $member) {
        if ($member['type'] === 'student') {
            $students[] = $member;
        }
    }
    return $students;
}

/**
 * Generate context ID for demo
 */
function generateDemoContextId() {
    return DEMO_COURSE_PREFIX . '-' . date('Y-m-d');
}

logMessage("Demo configuration loaded", [
    'course_code' => DEMO_COURSE_CODE,
    'course_title' => DEMO_COURSE_TITLE,
    'member_count' => count(DEMO_MEMBERS),
    'external_tool_domain' => EXTERNAL_TOOL_BASE_DOMAIN,
    'email_domain' => DEMO_EMAIL_DOMAIN
], 'DEMO_CONFIG');
?>