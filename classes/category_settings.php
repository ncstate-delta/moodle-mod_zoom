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
 * Category-level settings manager for Zoom YT.
 *
 * Handles retrieval and management of category-level Zoom account settings
 * with inheritance from parent categories and fallback to global settings.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt;

defined('MOODLE_INTERNAL') || die();

/**
 * Category settings manager class.
 */
class category_settings {

    /** @var int The category ID */
    protected $categoryid;

    /** @var object|null Cached settings object */
    protected $settings = null;

    /** @var int|null The category ID where settings were found (for inheritance tracking) */
    protected $settingssourcecategoryid = null;

    /**
     * Constructor.
     *
     * @param int $categoryid The category ID to get settings for.
     */
    public function __construct(int $categoryid) {
        $this->categoryid = $categoryid;
    }

    /**
     * Get category settings for a course.
     *
     * @param int $courseid The course ID.
     * @return category_settings The category settings instance for the course's category.
     */
    public static function get_for_course(int $courseid): self {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], 'category', MUST_EXIST);
        return new self($course->category);
    }

    /**
     * Get effective settings for this category, with inheritance.
     *
     * Walks up the category tree looking for settings. Falls back to global settings
     * if no category-level settings are found.
     *
     * @return object The effective settings object.
     */
    public function get_effective_settings(): object {
        if ($this->settings !== null) {
            return $this->settings;
        }

        global $DB;

        // Get the category path (includes all parent categories).
        $category = $DB->get_record('course_categories', ['id' => $this->categoryid], 'id, path', MUST_EXIST);

        // Parse the path to get category IDs from most specific to root.
        // Path format is like "/1/5/12" - we want [12, 5, 1].
        $pathparts = array_filter(explode('/', $category->path));
        $categoryids = array_reverse($pathparts);

        // Look for settings in each category, starting with the most specific.
        foreach ($categoryids as $catid) {
            $settings = $DB->get_record('zoomyt_category_settings', ['categoryid' => $catid]);

            if ($settings) {
                // Check if this category inherits from parent.
                if ($settings->inherit && $catid != $categoryids[count($categoryids) - 1]) {
                    // This category inherits, continue looking up the tree.
                    continue;
                }

                // Found non-inheriting settings or we're at root.
                // Check if it has actual credentials configured.
                if (!empty($settings->accountid) && !empty($settings->clientid) && !empty($settings->clientsecret)) {
                    $this->settings = $settings;
                    $this->settingssourcecategoryid = (int)$catid;
                    return $this->merge_with_global_defaults($settings);
                }
            }
        }

        // No category settings found, return global settings.
        $this->settings = $this->get_global_settings();
        $this->settingssourcecategoryid = null;
        return $this->settings;
    }

    /**
     * Get the category ID where the effective settings were found.
     *
     * @return int|null The category ID, or null if using global settings.
     */
    public function get_settings_source_category(): ?int {
        if ($this->settings === null) {
            $this->get_effective_settings();
        }
        return $this->settingssourcecategoryid;
    }

    /**
     * Check if this category is using its own settings (not inherited).
     *
     * @return bool True if using own settings.
     */
    public function has_own_settings(): bool {
        global $DB;

        $settings = $DB->get_record('zoomyt_category_settings', ['categoryid' => $this->categoryid]);
        return $settings && !$settings->inherit;
    }

    /**
     * Check if category-level settings are being used (vs global).
     *
     * @return bool True if using category settings.
     */
    public function is_using_category_settings(): bool {
        $this->get_effective_settings();
        return $this->settingssourcecategoryid !== null;
    }

    /**
     * Get Zoom API credentials for this category.
     *
     * @return object Object with accountid, clientid, clientsecret properties.
     */
    public function get_credentials(): object {
        $settings = $this->get_effective_settings();

        return (object)[
            'accountid' => $settings->accountid ?? '',
            'clientid' => $settings->clientid ?? '',
            'clientsecret' => $settings->clientsecret ?? '',
            'apiendpoint' => $settings->apiendpoint ?? 'global',
            'zoomurl' => $settings->zoomurl ?? '',
        ];
    }

    /**
     * Get default meeting settings for this category.
     *
     * @return object Object with default meeting settings.
     */
    public function get_meeting_defaults(): object {
        $settings = $this->get_effective_settings();

        return (object)[
            'recurring' => $settings->defaultrecurring ?? null,
            'waitingroom' => $settings->defaultwaitingroom ?? null,
            'joinbeforehost' => $settings->defaultjoinbeforehost ?? null,
            'audio' => $settings->defaultaudiooption ?? null,
            'hostvideo' => $settings->defaulthostvideo ?? null,
            'participantsvideo' => $settings->defaultparticipantsvideo ?? null,
            'autorecording' => $settings->defaultautorecording ?? null,
        ];
    }

    /**
     * Save category settings.
     *
     * @param object $data The settings data to save.
     * @return bool True on success.
     */
    public function save_settings(object $data): bool {
        global $DB, $USER;

        $now = time();
        $existing = $DB->get_record('zoomyt_category_settings', ['categoryid' => $this->categoryid]);

        $record = new \stdClass();
        $record->categoryid = $this->categoryid;
        $record->accountid = $data->accountid ?? '';
        $record->clientid = $data->clientid ?? '';
        $record->clientsecret = $data->clientsecret ?? '';
        $record->apiendpoint = $data->apiendpoint ?? 'global';
        $record->zoomurl = $data->zoomurl ?? '';
        $record->inherit = $data->inherit ?? 1;
        $record->defaultrecurring = $data->defaultrecurring ?? null;
        $record->defaultwaitingroom = $data->defaultwaitingroom ?? null;
        $record->defaultjoinbeforehost = $data->defaultjoinbeforehost ?? null;
        $record->defaultaudiooption = $data->defaultaudiooption ?? null;
        $record->defaulthostvideo = $data->defaulthostvideo ?? null;
        $record->defaultparticipantsvideo = $data->defaultparticipantsvideo ?? null;

        // YouTube settings.
        if (isset($data->yt_client_id)) {
            $record->yt_client_id = $data->yt_client_id;
        }
        if (isset($data->yt_client_secret)) {
            $record->yt_client_secret = $data->yt_client_secret;
        }
        if (isset($data->yt_refresh_token)) {
            $record->yt_refresh_token = $data->yt_refresh_token;
        }
        if (isset($data->yt_channel_id)) {
            $record->yt_channel_id = $data->yt_channel_id;
        }
        if (isset($data->yt_channel_name)) {
            $record->yt_channel_name = $data->yt_channel_name;
        }
        if (isset($data->yt_default_visibility)) {
            $record->yt_default_visibility = $data->yt_default_visibility;
        }
        if (isset($data->zoom_recording_delete_days)) {
            $record->zoom_recording_delete_days = $data->zoom_recording_delete_days ?: null;
        }

        $record->timemodified = $now;
        $record->usermodified = $USER->id;

        if ($existing) {
            $record->id = $existing->id;
            $record->timecreated = $existing->timecreated;
            // Preserve existing YouTube tokens if not provided.
            if (!isset($record->yt_refresh_token) && !empty($existing->yt_refresh_token)) {
                $record->yt_refresh_token = $existing->yt_refresh_token;
            }
            if (!isset($record->yt_channel_id) && !empty($existing->yt_channel_id)) {
                $record->yt_channel_id = $existing->yt_channel_id;
            }
            if (!isset($record->yt_channel_name) && !empty($existing->yt_channel_name)) {
                $record->yt_channel_name = $existing->yt_channel_name;
            }
            $DB->update_record('zoomyt_category_settings', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('zoomyt_category_settings', $record);
        }

        // Clear cached settings.
        $this->settings = null;
        $this->settingssourcecategoryid = null;

        // Clear the OAuth cache for this category.
        $this->clear_oauth_cache();

        return true;
    }

    /**
     * Get YouTube service for this category.
     *
     * @return \mod_zoomyt\youtube_service|null The YouTube service or null if not configured.
     */
    public function get_youtube_service(): ?\mod_zoomyt\youtube_service {
        global $CFG;
        require_once($CFG->dirroot . '/mod/zoomyt/classes/youtube_service.php');

        $settings = $this->get_effective_settings();

        if (empty($settings->yt_client_id) || empty($settings->yt_client_secret) || empty($settings->yt_refresh_token)) {
            return null;
        }

        return new \mod_zoomyt\youtube_service($settings, $this->settingssourcecategoryid);
    }

    /**
     * Check if YouTube is configured for this category.
     *
     * @return bool True if YouTube is configured.
     */
    public function has_youtube_configured(): bool {
        $settings = $this->get_effective_settings();
        return !empty($settings->yt_client_id) && !empty($settings->yt_client_secret) && !empty($settings->yt_refresh_token);
    }

    /**
     * Get YouTube settings for this category.
     *
     * @return object Object with YouTube settings.
     */
    public function get_youtube_settings(): object {
        $settings = $this->get_effective_settings();

        return (object)[
            'client_id' => $settings->yt_client_id ?? '',
            'client_secret' => $settings->yt_client_secret ?? '',
            'refresh_token' => $settings->yt_refresh_token ?? '',
            'channel_id' => $settings->yt_channel_id ?? '',
            'channel_name' => $settings->yt_channel_name ?? '',
            'default_visibility' => $settings->yt_default_visibility ?? 'unlisted',
            'zoom_recording_delete_days' => $settings->zoom_recording_delete_days ?? null,
        ];
    }

    /**
     * Delete category settings.
     *
     * @return bool True on success.
     */
    public function delete_settings(): bool {
        global $DB;

        $DB->delete_records('zoomyt_category_settings', ['categoryid' => $this->categoryid]);

        // Clear cached settings.
        $this->settings = null;
        $this->settingssourcecategoryid = null;

        return true;
    }

    /**
     * Get raw settings record for this category (without inheritance).
     *
     * @return object|null The settings record or null if not set.
     */
    public function get_raw_settings(): ?object {
        global $DB;

        $settings = $DB->get_record('zoomyt_category_settings', ['categoryid' => $this->categoryid]);
        return $settings ?: null;
    }

    /**
     * Clear the OAuth token cache for this category.
     */
    protected function clear_oauth_cache(): void {
        $cache = \cache::make('mod_zoomyt', 'oauth');
        $cache->delete('accesstoken_cat_' . $this->categoryid);
        $cache->delete('expires_cat_' . $this->categoryid);
        $cache->delete('scopes_cat_' . $this->categoryid);
    }

    /**
     * Get global settings as a settings-like object.
     *
     * @return object The global settings.
     */
    protected function get_global_settings(): object {
        $config = get_config('zoomyt');

        return (object)[
            'categoryid' => null,
            'accountid' => $config->accountid ?? '',
            'clientid' => $config->clientid ?? '',
            'clientsecret' => $config->clientsecret ?? '',
            'apiendpoint' => $config->apiendpoint ?? 'global',
            'zoomurl' => $config->zoomurl ?? '',
            'inherit' => 0,
            'defaultrecurring' => $config->defaultrecurring ?? 0,
            'defaultwaitingroom' => $config->defaultwaitingroomoption ?? 1,
            'defaultjoinbeforehost' => $config->defaultjoinbeforehost ?? 0,
            'defaultaudiooption' => $config->defaultaudiooption ?? 'both',
            'defaulthostvideo' => $config->defaulthostvideo ?? 0,
            'defaultparticipantsvideo' => $config->defaultparticipantsvideo ?? 0,
        ];
    }

    /**
     * Merge category settings with global defaults for any missing values.
     *
     * @param object $settings The category settings.
     * @return object The merged settings.
     */
    protected function merge_with_global_defaults(object $settings): object {
        $global = $this->get_global_settings();

        // Merge - category settings take priority, fall back to global for nulls.
        foreach (get_object_vars($global) as $key => $value) {
            if (!isset($settings->$key) || $settings->$key === null || $settings->$key === '') {
                // Don't override credentials with global if category has its own.
                if (!in_array($key, ['accountid', 'clientid', 'clientsecret']) || 
                    (empty($settings->accountid) && empty($settings->clientid))) {
                    $settings->$key = $value;
                }
            }
        }

        return $settings;
    }

    /**
     * Get all categories that have their own settings (for admin overview).
     *
     * @return array Array of category settings records.
     */
    public static function get_all_configured_categories(): array {
        global $DB;

        $sql = "SELECT cs.*, cc.name as categoryname, cc.path
                FROM {zoomyt_category_settings} cs
                JOIN {course_categories} cc ON cc.id = cs.categoryid
                WHERE cs.inherit = 0
                ORDER BY cc.sortorder";

        return $DB->get_records_sql($sql);
    }

    /**
     * Test the connection for this category's credentials.
     *
     * @return array ['success' => bool, 'message' => string]
     */
    public function test_connection(): array {
        $credentials = $this->get_credentials();

        if (empty($credentials->accountid) || empty($credentials->clientid) || empty($credentials->clientsecret)) {
            return [
                'success' => false,
                'message' => get_string('error_missing_credentials', 'zoomyt'),
            ];
        }

        try {
            // Create a webservice instance with these credentials.
            $service = new \mod_zoomyt\webservice($credentials);
            // Try to get the current user to test the connection.
            global $USER;
            $service->get_user(zoomyt_get_api_identifier($USER));

            return [
                'success' => true,
                'message' => get_string('connectionok', 'zoomyt'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('connectionfailed', 'zoomyt') . ' ' . $e->getMessage(),
            ];
        }
    }
}
