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
 * List of categories with Zoom YT settings management links.
 *
 * This page allows administrators to see all course categories and configure
 * category-level Zoom account settings for each one.
 *
 * @package    mod_zoom_yt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/mod/zoom_yt/classes/category_settings.php');

// Require site admin.
require_login();
require_capability('moodle/site:config', context_system::instance());

// Set up the page.
admin_externalpage_setup('modsettingzoom_yt');

$PAGE->set_url('/mod/zoom_yt/categorylist.php');
$PAGE->set_title(get_string('category_settings_list', 'zoom_yt'));
$PAGE->set_heading(get_string('category_settings_list', 'zoom_yt'));

// Get all categories.
$categories = core_course_category::get_all();

// Get all category settings.
$allsettings = \mod_zoom_yt\category_settings::get_all_configured_categories();
$configuredcategoryids = array_column($allsettings, 'categoryid');

// Output the page.
echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('category_settings_list', 'zoom_yt'));

echo html_writer::tag('p', get_string('category_settings_list_desc', 'zoom_yt'));

// Build the table.
$table = new html_table();
$table->head = [
    get_string('category'),
    get_string('status'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

foreach ($categories as $category) {
    $context = context_coursecat::instance($category->id);
    
    // Only show categories where the user has permission.
    if (!has_capability('mod/zoom_yt:managecategorysettings', $context)) {
        continue;
    }

    // Get the category's effective settings.
    $settingsmanager = new \mod_zoom_yt\category_settings($category->id);
    $rawsettings = $settingsmanager->get_raw_settings();
    $sourcecategory = $settingsmanager->get_settings_source_category();

    // Determine status.
    if ($rawsettings && !$rawsettings->inherit) {
        $status = html_writer::span(get_string('using_own_settings', 'zoom_yt'), 'badge badge-success');
    } else if ($sourcecategory !== null && $sourcecategory !== $category->id) {
        $parentcat = $DB->get_record('course_categories', ['id' => $sourcecategory], 'name');
        $status = html_writer::span(
            get_string('inheriting_from_category', 'zoom_yt', $parentcat->name),
            'badge badge-warning'
        );
    } else {
        $status = html_writer::span(get_string('using_global_settings', 'zoom_yt'), 'badge badge-info');
    }

    // Build category name with depth indication.
    $depth = substr_count($category->path, '/') - 1;
    $indent = str_repeat('&nbsp;&nbsp;&nbsp;', $depth);
    $categoryname = $indent . ($depth > 0 ? '↳ ' : '') . format_string($category->name);

    // Configure link.
    $configureurl = new moodle_url('/mod/zoom_yt/categorysettings.php', ['categoryid' => $category->id]);
    $configurelink = html_writer::link($configureurl, get_string('configure'), ['class' => 'btn btn-sm btn-primary']);

    $table->data[] = [
        $categoryname,
        $status,
        $configurelink,
    ];
}

if (empty($table->data)) {
    echo $OUTPUT->notification(get_string('no_categories_available', 'zoom_yt'), 'info');
} else {
    echo html_writer::table($table);
}

// Back to settings link.
$settingsurl = new moodle_url('/admin/settings.php', ['section' => 'modsettingzoom_yt']);
echo html_writer::div(
    html_writer::link($settingsurl, get_string('back_to_settings', 'zoom_yt'), ['class' => 'btn btn-secondary mt-3']),
    'mt-3'
);

echo $OUTPUT->footer();
