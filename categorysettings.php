<?php
// This file is part of the Zoom YT plugin for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Category-level Zoom YT settings management page.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/zoomyt/locallib.php');
require_once($CFG->dirroot . '/mod/zoomyt/classes/category_settings.php');
require_once($CFG->dirroot . '/mod/zoomyt/classes/form/category_settings_form.php');

$categoryid = required_param('categoryid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

// Get the category.
$category = $DB->get_record('course_categories', ['id' => $categoryid], '*', MUST_EXIST);
$context = context_coursecat::instance($categoryid);

// Require login and capability.
require_login();
require_capability('mod/zoomyt:managecategorysettings', $context);

// Set up the page.
$PAGE->set_context($context);
$PAGE->set_url('/mod/zoomyt/categorysettings.php', ['categoryid' => $categoryid]);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('categorysettings', 'zoomyt') . ': ' . $category->name);
$PAGE->set_heading($category->name);

// Navigation.
$PAGE->navbar->add(get_string('categories'), new moodle_url('/course/index.php'));
$PAGE->navbar->add($category->name, new moodle_url('/course/index.php', ['categoryid' => $categoryid]));
$PAGE->navbar->add(get_string('categorysettings', 'zoomyt'));

// Create settings manager.
$settingsmanager = new \mod_zoomyt\category_settings($categoryid);

// Handle test connection action.
if ($action === 'test' && confirm_sesskey()) {
    $result = $settingsmanager->test_connection();
    if ($result['success']) {
        \core\notification::success($result['message']);
    } else {
        \core\notification::error($result['message']);
    }
    redirect(new moodle_url('/mod/zoomyt/categorysettings.php', ['categoryid' => $categoryid]));
}

// Handle delete action.
if ($action === 'delete' && confirm_sesskey()) {
    $settingsmanager->delete_settings();
    \core\notification::success(get_string('categorysettings_deleted', 'zoomyt'));
    redirect(new moodle_url('/mod/zoomyt/categorysettings.php', ['categoryid' => $categoryid]));
}

// Create the form.
$form = new \mod_zoomyt\form\category_settings_form(null, [
    'categoryid' => $categoryid,
    'category' => $category,
]);

// Load existing settings.
$existingsettings = $settingsmanager->get_raw_settings();
if ($existingsettings) {
    $form->set_data($existingsettings);
}

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/index.php', ['categoryid' => $categoryid]));
} else if ($data = $form->get_data()) {
    $settingsmanager->save_settings($data);
    \core\notification::success(get_string('categorysettings_saved', 'zoomyt'));
    redirect(new moodle_url('/mod/zoomyt/categorysettings.php', ['categoryid' => $categoryid]));
}

// Output the page.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('categorysettings', 'zoomyt'));

// Show current inheritance info.
$effectivesettings = $settingsmanager->get_effective_settings();
$sourcecategoryid = $settingsmanager->get_settings_source_category();

if ($sourcecategoryid === null) {
    $inheritanceinfo = get_string('using_global_settings', 'zoomyt');
    $inheritanceclass = 'alert-info';
} else if ($sourcecategoryid === $categoryid) {
    $inheritanceinfo = get_string('using_own_settings', 'zoomyt');
    $inheritanceclass = 'alert-success';
} else {
    $sourcecategory = $DB->get_record('course_categories', ['id' => $sourcecategoryid], 'name');
    $inheritanceinfo = get_string('inheriting_from_category', 'zoomyt', $sourcecategory->name);
    $inheritanceclass = 'alert-warning';
}

echo html_writer::div($inheritanceinfo, 'alert ' . $inheritanceclass);

// Show connection test button if settings exist.
if ($existingsettings && !$existingsettings->inherit) {
    $testurl = new moodle_url('/mod/zoomyt/categorysettings.php', [
        'categoryid' => $categoryid,
        'action' => 'test',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::div(
        html_writer::link($testurl, get_string('testconnection', 'zoomyt'), ['class' => 'btn btn-secondary mb-3']),
        'mb-3'
    );
}

// Display the form.
$form->display();

// Show delete button if settings exist.
if ($existingsettings) {
    echo html_writer::start_div('mt-4 pt-3 border-top');
    $deleteurl = new moodle_url('/mod/zoomyt/categorysettings.php', [
        'categoryid' => $categoryid,
        'action' => 'delete',
        'sesskey' => sesskey(),
    ]);
    echo html_writer::link(
        $deleteurl,
        get_string('deletecategorysettings', 'zoomyt'),
        [
            'class' => 'btn btn-danger',
            'onclick' => "return confirm('" . get_string('deletecategorysettings_confirm', 'zoomyt') . "');",
        ]
    );
    echo html_writer::end_div();
}

echo $OUTPUT->footer();
