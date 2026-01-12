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
 * YouTube API service class for Zoom YT.
 *
 * Handles OAuth authentication and video uploads to YouTube.
 *
 * @package    mod_zoomyt
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_zoomyt;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * YouTube API service class.
 */
class youtube_service {

    /** @var string YouTube OAuth token endpoint */
    const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** @var string YouTube API base URL */
    const API_URL = 'https://www.googleapis.com/youtube/v3';

    /** @var string YouTube upload URL */
    const UPLOAD_URL = 'https://www.googleapis.com/upload/youtube/v3/videos';

    /** @var string Client ID */
    protected $clientid;

    /** @var string Client secret */
    protected $clientsecret;

    /** @var string Refresh token */
    protected $refreshtoken;

    /** @var string Access token */
    protected $accesstoken;

    /** @var int Category ID for cache keying */
    protected $categoryid;

    /**
     * Constructor.
     *
     * @param object $credentials Object with yt_client_id, yt_client_secret, yt_refresh_token.
     * @param int|null $categoryid Category ID for caching.
     */
    public function __construct(object $credentials, ?int $categoryid = null) {
        $this->clientid = $credentials->yt_client_id ?? '';
        $this->clientsecret = $credentials->yt_client_secret ?? '';
        $this->refreshtoken = $credentials->yt_refresh_token ?? '';
        $this->categoryid = $categoryid;
    }

    /**
     * Get a YouTube service instance for a course.
     *
     * @param int $courseid The course ID.
     * @return self|null The YouTube service or null if not configured.
     */
    public static function get_instance_for_course(int $courseid): ?self {
        global $CFG;
        require_once($CFG->dirroot . '/mod/zoomyt/classes/category_settings.php');

        // Use get_for_course to properly convert course ID to category settings.
        $catsettings = category_settings::get_for_course($courseid);
        $settings = $catsettings->get_effective_settings();

        if (empty($settings->yt_client_id) || empty($settings->yt_client_secret) || empty($settings->yt_refresh_token)) {
            return null;
        }

        return new self($settings, $catsettings->get_settings_source_category());
    }

    /**
     * Get a YouTube service instance for a specific activity.
     * Checks activity -> category -> site settings in that order.
     *
     * @param int $zoomid The zoom activity instance ID.
     * @return self|null The YouTube service or null if not configured.
     */
    public static function get_instance_for_activity(int $zoomid): ?self {
        global $CFG, $DB;

        // Get the zoom activity record.
        $zoom = $DB->get_record('zoomyt', ['id' => $zoomid]);
        if (!$zoom) {
            return null;
        }

        // Site-wide client credentials (always used).
        $clientid = get_config('zoomyt', 'youtube_client_id');
        $clientsecret = get_config('zoomyt', 'youtube_client_secret');

        // If no site-wide credentials, YouTube is not configured at all.
        if (empty($clientid) || empty($clientsecret)) {
            return null;
        }

        $refreshtoken = null;
        $categoryid = null;

        // 1. Check activity-level settings first.
        if (empty($zoom->yt_use_category) && !empty($zoom->yt_refresh_token)) {
            // Activity has its own YouTube channel configured.
            $refreshtoken = $zoom->yt_refresh_token;
        }

        // 2. If not, check category-level settings (walk up the category tree).
        if (empty($refreshtoken)) {
            $course = $DB->get_record('course', ['id' => $zoom->course], 'category', MUST_EXIST);

            // Get category path and walk up looking for YouTube settings.
            $category = $DB->get_record('course_categories', ['id' => $course->category], 'id, path');
            if ($category) {
                $pathparts = array_filter(explode('/', $category->path));
                $categoryids = array_reverse($pathparts); // Most specific first.

                foreach ($categoryids as $catid) {
                    $catsettings = $DB->get_record('zoomyt_category_settings', ['categoryid' => $catid]);

                    if ($catsettings && !empty($catsettings->yt_refresh_token)) {
                        // Check if this category inherits YouTube settings.
                        if (!empty($catsettings->inherit_youtube) && $catid != end($categoryids)) {
                            // This category inherits YouTube, continue looking up.
                            continue;
                        }

                        // Found YouTube settings at this category level.
                        $refreshtoken = $catsettings->yt_refresh_token;
                        $categoryid = (int)$catid;
                        break;
                    }
                }
            }
        }

        // 3. If still not found, check site-wide settings.
        if (empty($refreshtoken)) {
            $refreshtoken = get_config('zoomyt', 'youtube_default_refresh_token');
        }

        // If no refresh token at any level, YouTube is not configured.
        if (empty($refreshtoken)) {
            return null;
        }

        // Create credentials object.
        $credentials = new \stdClass();
        $credentials->yt_client_id = $clientid;
        $credentials->yt_client_secret = $clientsecret;
        $credentials->yt_refresh_token = $refreshtoken;

        return new self($credentials, $categoryid);
    }

    /**
     * Check if YouTube is configured.
     *
     * @return bool True if configured.
     */
    public function is_configured(): bool {
        return !empty($this->clientid) && !empty($this->clientsecret) && !empty($this->refreshtoken);
    }

    /**
     * Get the OAuth authorization URL for initial setup.
     *
     * @param string $redirecturi The redirect URI after authorization.
     * @param string $state State parameter for CSRF protection.
     * @return string The authorization URL.
     */
    public static function get_auth_url(string $clientid, string $redirecturi, string $state): string {
        $params = [
            'client_id' => $clientid,
            'redirect_uri' => $redirecturi,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube.readonly',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for tokens.
     *
     * @param string $code The authorization code.
     * @param string $redirecturi The redirect URI.
     * @param string $clientid Client ID.
     * @param string $clientsecret Client secret.
     * @return object Token response with access_token, refresh_token, etc.
     * @throws \moodle_exception On error.
     */
    public static function exchange_code_for_tokens(
        string $code,
        string $redirecturi,
        string $clientid,
        string $clientsecret
    ): object {
        $curl = new \curl();

        $data = [
            'code' => $code,
            'client_id' => $clientid,
            'client_secret' => $clientsecret,
            'redirect_uri' => $redirecturi,
            'grant_type' => 'authorization_code',
        ];

        $response = $curl->post(self::TOKEN_URL, $data);

        if ($curl->get_errno()) {
            throw new \moodle_exception('youtube_oauth_error', 'zoomyt', '', $curl->error);
        }

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_oauth_error', 'zoomyt', '', $result->error_description ?? $result->error);
        }

        return $result;
    }

    /**
     * Get a valid access token, refreshing if necessary.
     *
     * @return string The access token.
     * @throws \moodle_exception On error.
     */
    protected function get_access_token(): string {
        if (!empty($this->accesstoken)) {
            return $this->accesstoken;
        }

        // Check cache.
        $cache = \cache::make('mod_zoomyt', 'oauth');
        $cachekey = 'yt_' . ($this->categoryid ?? 'global') . '_accesstoken';
        $expireskey = 'yt_' . ($this->categoryid ?? 'global') . '_expires';

        $token = $cache->get($cachekey);
        $expires = $cache->get($expireskey);

        if (!empty($token) && !empty($expires) && time() < $expires) {
            $this->accesstoken = $token;
            return $token;
        }

        // Refresh the token.
        $curl = new \curl();

        $data = [
            'client_id' => $this->clientid,
            'client_secret' => $this->clientsecret,
            'refresh_token' => $this->refreshtoken,
            'grant_type' => 'refresh_token',
        ];

        $response = $curl->post(self::TOKEN_URL, $data);

        if ($curl->get_errno()) {
            throw new \moodle_exception('youtube_oauth_error', 'zoomyt', '', $curl->error);
        }

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_oauth_error', 'zoomyt', '', $result->error_description ?? $result->error);
        }

        $this->accesstoken = $result->access_token;
        $expires = time() + ($result->expires_in ?? 3600) - 60; // 60 seconds buffer.

        $cache->set($cachekey, $this->accesstoken);
        $cache->set($expireskey, $expires);

        return $this->accesstoken;
    }

    /**
     * Get channel information.
     *
     * @return object Channel info with id, title, etc.
     * @throws \moodle_exception On error.
     */
    public function get_channel_info(): object {
        $token = $this->get_access_token();

        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $token);

        $url = self::API_URL . '/channels?part=snippet&mine=true';
        $response = $curl->get($url);

        if ($curl->get_errno()) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $curl->error);
        }

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $result->error->message ?? 'Unknown error');
        }

        if (empty($result->items)) {
            throw new \moodle_exception('youtube_no_channel', 'zoomyt');
        }

        $channel = $result->items[0];
        return (object)[
            'id' => $channel->id,
            'title' => $channel->snippet->title,
            'description' => $channel->snippet->description ?? '',
            'thumbnail' => $channel->snippet->thumbnails->default->url ?? '',
        ];
    }

    /**
     * Upload a video to YouTube.
     *
     * @param string $filepath Path to the video file.
     * @param string $title Video title.
     * @param string $description Video description.
     * @param string $visibility Visibility: public, unlisted, private.
     * @param callable|null $progresscallback Optional callback for progress updates.
     * @return object Video info with id, url, etc.
     * @throws \moodle_exception On error.
     */
    public function upload_video(
        string $filepath,
        string $title,
        string $description = '',
        string $visibility = 'unlisted',
        ?callable $progresscallback = null
    ): object {
        if (!file_exists($filepath)) {
            throw new \moodle_exception('youtube_file_not_found', 'zoomyt', '', $filepath);
        }

        $token = $this->get_access_token();
        $filesize = filesize($filepath);

        // Step 1: Initialize resumable upload.
        $metadata = [
            'snippet' => [
                'title' => substr($title, 0, 100),
                'description' => substr($description, 0, 5000),
                'categoryId' => '27', // Education category.
            ],
            'status' => [
                'privacyStatus' => $visibility,
                'selfDeclaredMadeForKids' => false,
            ],
        ];

        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $token);
        $curl->setHeader('Content-Type: application/json; charset=UTF-8');
        $curl->setHeader('X-Upload-Content-Length: ' . $filesize);
        $curl->setHeader('X-Upload-Content-Type: video/mp4');

        $initurl = self::UPLOAD_URL . '?uploadType=resumable&part=snippet,status';
        $response = $curl->post($initurl, json_encode($metadata));
        $info = $curl->get_info();

        if ($info['http_code'] !== 200) {
            $error = json_decode($response);
            throw new \moodle_exception('youtube_upload_init_error', 'zoomyt', '', 
                $error->error->message ?? 'HTTP ' . $info['http_code']);
        }

        // Get the upload URL from response headers.
        $headers = $curl->getResponse();
        $uploadurl = $headers['location'] ?? $headers['Location'] ?? null;

        if (empty($uploadurl)) {
            throw new \moodle_exception('youtube_upload_no_location', 'zoomyt');
        }

        // Step 2: Upload the video file.
        $handle = fopen($filepath, 'rb');
        if (!$handle) {
            throw new \moodle_exception('youtube_file_open_error', 'zoomyt', '', $filepath);
        }

        $chunksize = 10 * 1024 * 1024; // 10MB chunks.
        $uploaded = 0;

        while (!feof($handle)) {
            $chunk = fread($handle, $chunksize);
            $chunklen = strlen($chunk);
            $end = $uploaded + $chunklen - 1;

            $curl = new \curl();
            $curl->setHeader('Authorization: Bearer ' . $token);
            $curl->setHeader('Content-Type: video/mp4');
            $curl->setHeader('Content-Length: ' . $chunklen);
            $curl->setHeader('Content-Range: bytes ' . $uploaded . '-' . $end . '/' . $filesize);

            $curl->setopt(['CURLOPT_POSTFIELDS' => $chunk]);
            $response = $curl->put($uploadurl, $chunk);
            $info = $curl->get_info();

            $uploaded += $chunklen;

            if ($progresscallback) {
                $progresscallback($uploaded, $filesize);
            }

            // 308 = Resume Incomplete (continue uploading).
            // 200/201 = Upload complete.
            if ($info['http_code'] !== 308 && $info['http_code'] !== 200 && $info['http_code'] !== 201) {
                fclose($handle);
                $error = json_decode($response);
                throw new \moodle_exception('youtube_upload_chunk_error', 'zoomyt', '', 
                    $error->error->message ?? 'HTTP ' . $info['http_code']);
            }

            if ($info['http_code'] === 200 || $info['http_code'] === 201) {
                // Upload complete.
                break;
            }
        }

        fclose($handle);

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_upload_error', 'zoomyt', '', $result->error->message);
        }

        // Get video details including thumbnail.
        $videoinfo = $this->get_video_info($result->id);

        return (object)[
            'id' => $result->id,
            'url' => 'https://www.youtube.com/watch?v=' . $result->id,
            'title' => $result->snippet->title ?? $title,
            'description' => $result->snippet->description ?? $description,
            'thumbnail_url' => $videoinfo->thumbnail_url ?? '',
            'duration' => $videoinfo->duration ?? 0,
        ];
    }

    /**
     * Get video information from YouTube.
     *
     * @param string $videoid YouTube video ID.
     * @return object Video info.
     * @throws \moodle_exception On error.
     */
    public function get_video_info(string $videoid): object {
        $token = $this->get_access_token();

        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $token);

        $url = self::API_URL . '/videos?part=snippet,contentDetails,status&id=' . $videoid;
        $response = $curl->get($url);

        if ($curl->get_errno()) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $curl->error);
        }

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $result->error->message ?? 'Unknown error');
        }

        if (empty($result->items)) {
            throw new \moodle_exception('youtube_video_not_found', 'zoomyt', '', $videoid);
        }

        $video = $result->items[0];

        // Parse ISO 8601 duration.
        $duration = 0;
        if (!empty($video->contentDetails->duration)) {
            $interval = new \DateInterval($video->contentDetails->duration);
            $duration = $interval->h * 3600 + $interval->i * 60 + $interval->s;
        }

        // Get best thumbnail.
        $thumbnails = $video->snippet->thumbnails ?? new \stdClass();
        $thumbnailurl = $thumbnails->maxres->url 
            ?? $thumbnails->high->url 
            ?? $thumbnails->medium->url 
            ?? $thumbnails->default->url 
            ?? '';

        return (object)[
            'id' => $video->id,
            'title' => $video->snippet->title ?? '',
            'description' => $video->snippet->description ?? '',
            'thumbnail_url' => $thumbnailurl,
            'duration' => $duration,
            'visibility' => $video->status->privacyStatus ?? 'unlisted',
            'published_at' => $video->snippet->publishedAt ?? '',
        ];
    }

    /**
     * Update video visibility.
     *
     * @param string $videoid YouTube video ID.
     * @param string $visibility New visibility: public, unlisted, private.
     * @return bool True on success.
     * @throws \moodle_exception On error.
     */
    public function update_video_visibility(string $videoid, string $visibility): bool {
        $token = $this->get_access_token();

        $curl = new \curl();
        $curl->setHeader('Authorization: Bearer ' . $token);
        $curl->setHeader('Content-Type: application/json');

        $data = [
            'id' => $videoid,
            'status' => [
                'privacyStatus' => $visibility,
            ],
        ];

        $url = self::API_URL . '/videos?part=status';
        $response = $curl->put($url, json_encode($data));

        if ($curl->get_errno()) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $curl->error);
        }

        $result = json_decode($response);

        if (isset($result->error)) {
            throw new \moodle_exception('youtube_api_error', 'zoomyt', '', $result->error->message ?? 'Unknown error');
        }

        return true;
    }

    /**
     * Test the YouTube connection.
     *
     * @return array ['success' => bool, 'message' => string, 'channel' => object|null]
     */
    public function test_connection(): array {
        if (!$this->is_configured()) {
            return [
                'success' => false,
                'message' => get_string('youtube_not_configured', 'zoomyt'),
                'channel' => null,
            ];
        }

        try {
            $channel = $this->get_channel_info();
            return [
                'success' => true,
                'message' => get_string('youtube_connection_ok', 'zoomyt', $channel->title),
                'channel' => $channel,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => get_string('youtube_connection_failed', 'zoomyt') . ' ' . $e->getMessage(),
                'channel' => null,
            ];
        }
    }
}
