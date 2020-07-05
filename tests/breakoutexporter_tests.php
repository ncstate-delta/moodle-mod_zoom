<?php

defined('MOODLE_INTERNAL') || die();

class breakoutexporter_tests extends \advanced_testcase
{
    protected $groupings = [];
    protected $groups = [];
    protected $gpg1users = [];
    protected $gpg2users = [];
    protected $students = [];
    protected $course;

    public function setUp() {
        $this->resetAfterTest();
        set_config('apikey', 'test', 'mod_zoom');
        set_config('apisecret', 'test', 'mod_zoom');

        $g = $this->getDataGenerator();
        $this->course = $g->create_course();
        $this->groupings[] = $g->create_grouping(['name' => 'Grouping 1']);
        $this->groupings[] = $g->create_grouping(['name' => 'Grouping 2']);

        // 10 students equallyt between groupings 1 & 2.
        for($i = 0; $i < 16; $i++) {
            $u = $g->create_and_enrol($this->course);
            $this->students[] = $u;
            if ($i % 2) {
                $this->gpg1users[] = $u;
            } else {
                $this->gpg2users[] = $u;
            }
        }

        // TODO Some staff.

        // 4 groups in grouping.
        // 16 students equally assigned to each group (4 per group)
        // Grouping 1 groups.
        $gpg1 = $this->groupings[0];
        for($i = 0; $i < 4; $i++) {
            $group = $g->create_group(['name' => "Grouping 1- Group {$i}"]);
            $g->create_grouping_group(['groupingid' => $gpg1->id, 'groupid' => $group->id]);
            $this->groups[] = $g;
            for($j = 0; $j < 4; $j++) {
                $g->create_group_member([
                    'userid' => $this->gpg1users[$i*$j],
                    'groupid' => $group->id
                ]);
            }
            $this->assetEquals(4, groups_get_members($group->id));
        }
        $this->assertEquals(4, groups_get_all_groups($this->course->id, 0, $gpg1->id));

        /*
        // Grouping 2 groups
        $gpg2 = $this->groupings[1];
        for($i = 0; $i < 4; $i++) {
            $group = $g->create_group(['name' => "Grouping 2- Group {$i}"]);
            $g->create_grouping_group(['groupingid' => $gpg2->id, 'groupid' => $group->id]);
            $this->groups[] = $g;
        }
        */
    }

    public function test_breakoutexport() {
        $groupingtoexport = $this->groupings[0];    // Grouping 1
        $zoom = [
            'breakoutgrouping' => $groupingtoexport
        ];
        $exporter = new \mod_zoom\local\breakoutexporter(
            $zoom
        );

        $exporter->export()

    }
}