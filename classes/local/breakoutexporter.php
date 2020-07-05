<?php


namespace mod_zoom\local;
use csv_export_writer;

class breakoutexporter
{
    private $zoom;
    private $course;
    private $cm;
    private $grouping;
    private $groups;
    private $groupmembers;
    /**
     * @var list of users that we've already seen, and the groups they're in.
     */
    private $seenbefore;

    /**
     * @var bool|int False if the data has never been loaded or a timestamp for when it was updated last.
     */
    private $dataloaded = false;

    public function __construct(StdClass $zoom)
    {
        $this->zoom = zoom;
        list($course, $cm) = get_course_and_cm_from_instance($zoom, 'zoom');
        $this->course = $course;
        $this->cm = $cm;
    }

    /**
     * Get All the data we need for export.
     */
    public function filldata() {
        // Grouping details.
        $this->grouping = groups_get_grouping($this->zoom->breakoutgrouping);

        // List of groups in the grouping
        $this->groups = groups_get_all_groups($this->course->id, $this->grouping->id);

        if (!empty($this->groups)) {
            $members = [];
            foreach($this->groups as $group) {
                $gms = groups_get_members($group->id);
                $members[$group->id] = [];
                foreach($gms as $user) {
                    if (!isset($this->seenbefore[$user->id])) {
                        $this->seenbefore[$user->id] = [$group->id];
                        $members[$group->id][] = $user;
                    } else {
                        $this->seenbefore[$user->id][] = $group->id;
                    }
                }
            }
            $this->groupmembers = $members;
        }
        $this->dataloaded = time();
    }

    /**
     * Preview the data.
     * @param int $limit
     * @return array
     */
    public function preview() {
        $data = [];
        foreach($this->groups as $group) {
            $groupname = $group->name;
            foreach($this->groupmembers[$group->id] as $members) {
                $data[] = [$groupname, $members->email];
            }
        }
        return $data;
    }

    /**
     * Allow the data to get this old before we refresh it.
     */
    const MAX_LIFESPAN = 1800;
    /**
     * Perform the real export action to the file.
     * @param $filename
     * @return void|string
     */
    public function export($filenameorpreview = '', $preview = false) {
        if ($this->dataloaded) {
            $age = time() - $this->dataloaded;
            if ($age >= self::MAX_LIFESPAN) {
                $this->filldata();
            }
        } else {
            $this->filldata();
        }
        if (!$this->dataloaded) {
            // Something's gone wrong.
            throw new \coding_exception("Data was not loaded for some reason");
        }
        $exp = null;
        $previewdata = [];
        if (!$preview) {
            $exp = new csv_export_writer();
        }
        foreach($this->groups as $group) {
            $groupname = $group->name;
            foreach($this->groupmembers[$group->id] as $members) {
                if ($preview) {
                    $previewdata[] = [$groupname, $members->email];
                } else {
                    $exp->add_data([$groupname, $members->email]);
                }
            }
        }

        // Finally do output.
        if ($preview) {
            return $previewdata;
        } else {
            $exp->set_filename($filename);
            $exp->download_file();
        }
    }
}