<?php
// This file is part of Moodle - http://moodle.org/
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
 * relationship library tests.
 *
 * @package    core_relationship
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/relationship/lib.php");


/**
 * relationship library tests.
 *
 * @package    core_relationship
 * @category   phpunit
 * @copyright  2012 Petr Skoda {@link http://skodak.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_relationship_relationshiplib_testcase extends advanced_testcase {

    public function test_relationship_add_relationship() {
        global $DB;

        $this->resetAfterTest();

        $relationship = new stdClass();
        $relationship->contextid = context_system::instance()->id;
        $relationship->name = 'test relationship';
        $relationship->idnumber = 'testid';
        $relationship->description = 'test relationship desc';
        $relationship->descriptionformat = FORMAT_HTML;

        $id = relationship_add_relationship($relationship);
        $this->assertNotEmpty($id);

        $newrelationship = $DB->get_record('relationship', array('id'=>$id));
        $this->assertEquals($relationship->contextid, $newrelationship->contextid);
        $this->assertSame($relationship->name, $newrelationship->name);
        $this->assertSame($relationship->description, $newrelationship->description);
        $this->assertEquals($relationship->descriptionformat, $newrelationship->descriptionformat);
        $this->assertNotEmpty($newrelationship->timecreated);
        $this->assertSame($newrelationship->component, '');
        $this->assertSame($newrelationship->timecreated, $newrelationship->timemodified);
    }

    public function test_relationship_add_relationship_missing_name() {
        $relationship = new stdClass();
        $relationship->contextid = context_system::instance()->id;
        $relationship->name = null;
        $relationship->idnumber = 'testid';
        $relationship->description = 'test relationship desc';
        $relationship->descriptionformat = FORMAT_HTML;

        $this->setExpectedException('coding_exception', 'Missing relationship name in relationship_add_relationship().');
        relationship_add_relationship($relationship);
    }

    public function test_relationship_add_relationship_event() {
        $this->resetAfterTest();

        // Setup relationship data structure.
        $relationship = new stdClass();
        $relationship->contextid = context_system::instance()->id;
        $relationship->name = 'test relationship';
        $relationship->idnumber = 'testid';
        $relationship->description = 'test relationship desc';
        $relationship->descriptionformat = FORMAT_HTML;

        // Catch Events.
        $sink = $this->redirectEvents();

        // Perform the add operation.
        $id = relationship_add_relationship($relationship);

        // Capture the event.
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\core\event\relationship_created', $event);
        $this->assertEquals('relationship', $event->objecttable);
        $this->assertEquals($id, $event->objectid);
        $this->assertEquals($relationship->contextid, $event->contextid);
        $this->assertEquals($relationship, $event->get_record_snapshot('relationship', $id));
        $this->assertEventLegacyData($relationship, $event);
    }

    public function test_relationship_update_relationship() {
        global $DB;

        $this->resetAfterTest();

        $relationship = new stdClass();
        $relationship->contextid = context_system::instance()->id;
        $relationship->name = 'test relationship';
        $relationship->idnumber = 'testid';
        $relationship->description = 'test relationship desc';
        $relationship->descriptionformat = FORMAT_HTML;
        $id = relationship_add_relationship($relationship);
        $this->assertNotEmpty($id);
        $DB->set_field('relationship', 'timecreated', $relationship->timecreated - 10, array('id'=>$id));
        $DB->set_field('relationship', 'timemodified', $relationship->timemodified - 10, array('id'=>$id));
        $relationship = $DB->get_record('relationship', array('id'=>$id));

        $relationship->name = 'test relationship 2';
        relationship_update_relationship($relationship);

        $newrelationship = $DB->get_record('relationship', array('id'=>$id));

        $this->assertSame($relationship->contextid, $newrelationship->contextid);
        $this->assertSame($relationship->name, $newrelationship->name);
        $this->assertSame($relationship->description, $newrelationship->description);
        $this->assertSame($relationship->descriptionformat, $newrelationship->descriptionformat);
        $this->assertSame($relationship->timecreated, $newrelationship->timecreated);
        $this->assertSame($relationship->component, $newrelationship->component);
        $this->assertGreaterThan($newrelationship->timecreated, $newrelationship->timemodified);
        $this->assertLessThanOrEqual(time(), $newrelationship->timemodified);
    }

    public function test_relationship_update_relationship_event() {
        global $DB;

        $this->resetAfterTest();

        // Setup the relationship data structure.
        $relationship = new stdClass();
        $relationship->contextid = context_system::instance()->id;
        $relationship->name = 'test relationship';
        $relationship->idnumber = 'testid';
        $relationship->description = 'test relationship desc';
        $relationship->descriptionformat = FORMAT_HTML;
        $id = relationship_add_relationship($relationship);
        $this->assertNotEmpty($id);

        $relationship->name = 'test relationship 2';

        // Catch Events.
        $sink = $this->redirectEvents();

        // Peform the update.
        relationship_update_relationship($relationship);

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $updatedrelationship = $DB->get_record('relationship', array('id'=>$id));
        $this->assertInstanceOf('\core\event\relationship_updated', $event);
        $this->assertEquals('relationship', $event->objecttable);
        $this->assertEquals($updatedrelationship->id, $event->objectid);
        $this->assertEquals($updatedrelationship->contextid, $event->contextid);
        $this->assertEquals($relationship, $event->get_record_snapshot('relationship', $id));
        $this->assertEventLegacyData($relationship, $event);
    }

    public function test_relationship_delete_relationship() {
        global $DB;

        $this->resetAfterTest();

        $relationship = $this->getDataGenerator()->create_relationship();

        relationship_delete_relationship($relationship);

        $this->assertFalse($DB->record_exists('relationship', array('id'=>$relationship->id)));
    }

    public function test_relationship_delete_relationship_event() {

        $this->resetAfterTest();

        $relationship = $this->getDataGenerator()->create_relationship();

        // Capture the events.
        $sink = $this->redirectEvents();

        // Perform the delete.
        relationship_delete_relationship($relationship);

        $events = $sink->get_events();
        $sink->close();

        // Validate the event structure.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\core\event\relationship_deleted', $event);
        $this->assertEquals('relationship', $event->objecttable);
        $this->assertEquals($relationship->id, $event->objectid);
        $this->assertEquals($relationship, $event->get_record_snapshot('relationship', $relationship->id));
        $this->assertEventLegacyData($relationship, $event);
    }

    public function test_relationship_delete_category() {
        global $DB;

        $this->resetAfterTest();

        $category = $this->getDataGenerator()->create_category();

        $relationship = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category->id)->id));

        relationship_delete_category($category);

        $this->assertTrue($DB->record_exists('relationship', array('id'=>$relationship->id)));
        $newrelationship = $DB->get_record('relationship', array('id'=>$relationship->id));
        $this->assertEquals(context_system::instance()->id, $newrelationship->contextid);
    }

    public function test_relationship_add_member() {
        global $DB;

        $this->resetAfterTest();

        $relationship = $this->getDataGenerator()->create_relationship();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse($DB->record_exists('relationship_members', array('relationshipid'=>$relationship->id, 'userid'=>$user->id)));
        relationship_add_member($relationship->id, $user->id);
        $this->assertTrue($DB->record_exists('relationship_members', array('relationshipid'=>$relationship->id, 'userid'=>$user->id)));
    }

    public function test_relationship_add_member_event() {
        global $USER;
        $this->resetAfterTest();

        // Setup the data.
        $relationship = $this->getDataGenerator()->create_relationship();
        $user = $this->getDataGenerator()->create_user();

        // Capture the events.
        $sink = $this->redirectEvents();

        // Peform the add member operation.
        relationship_add_member($relationship->id, $user->id);

        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\core\event\relationship_member_added', $event);
        $this->assertEquals('relationship', $event->objecttable);
        $this->assertEquals($relationship->id, $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEventLegacyData((object) array('relationshipid' => $relationship->id, 'userid' => $user->id), $event);
    }

    public function test_relationship_remove_member() {
        global $DB;

        $this->resetAfterTest();

        $relationship = $this->getDataGenerator()->create_relationship();
        $user = $this->getDataGenerator()->create_user();

        relationship_add_member($relationship->id, $user->id);
        $this->assertTrue($DB->record_exists('relationship_members', array('relationshipid'=>$relationship->id, 'userid'=>$user->id)));

        relationship_remove_member($relationship->id, $user->id);
        $this->assertFalse($DB->record_exists('relationship_members', array('relationshipid'=>$relationship->id, 'userid'=>$user->id)));
    }

    public function test_relationship_remove_member_event() {
        global $USER;
        $this->resetAfterTest();

        // Setup the data.
        $relationship = $this->getDataGenerator()->create_relationship();
        $user = $this->getDataGenerator()->create_user();
        relationship_add_member($relationship->id, $user->id);

        // Capture the events.
        $sink = $this->redirectEvents();

        // Peform the remove operation.
        relationship_remove_member($relationship->id, $user->id);
        $events = $sink->get_events();
        $sink->close();

        // Validate the event.
        $this->assertCount(1, $events);
        $event = $events[0];
        $this->assertInstanceOf('\core\event\relationship_member_removed', $event);
        $this->assertEquals('relationship', $event->objecttable);
        $this->assertEquals($relationship->id, $event->objectid);
        $this->assertEquals($user->id, $event->relateduserid);
        $this->assertEquals($USER->id, $event->userid);
        $this->assertEventLegacyData((object) array('relationshipid' => $relationship->id, 'userid' => $user->id), $event);
    }

    public function test_relationship_is_member() {
        global $DB;

        $this->resetAfterTest();

        $relationship = $this->getDataGenerator()->create_relationship();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(relationship_is_member($relationship->id, $user->id));
        relationship_add_member($relationship->id, $user->id);
        $this->assertTrue(relationship_is_member($relationship->id, $user->id));
    }

    public function test_relationship_get_visible_list() {
        global $DB;

        $this->resetAfterTest();

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $course1 = $this->getDataGenerator()->create_course(array('category'=>$category1->id));
        $course2 = $this->getDataGenerator()->create_course(array('category'=>$category2->id));
        $course3 = $this->getDataGenerator()->create_course();

        $relationship1 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category1->id)->id));
        $relationship2 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category2->id)->id));
        $relationship3 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_system::instance()->id));
        $relationship4 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_system::instance()->id));

        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $user3 = $this->getDataGenerator()->create_user();
        $user4 = $this->getDataGenerator()->create_user();
        $user5 = $this->getDataGenerator()->create_user();

        $manualenrol = enrol_get_plugin('manual');
        $enrol1 = $DB->get_record('enrol', array('courseid'=>$course1->id, 'enrol'=>'manual'));
        $enrol2 = $DB->get_record('enrol', array('courseid'=>$course2->id, 'enrol'=>'manual'));

        $manualenrol->enrol_user($enrol1, $user1->id);
        $manualenrol->enrol_user($enrol1, $user3->id);
        $manualenrol->enrol_user($enrol1, $user4->id);
        $manualenrol->enrol_user($enrol2, $user2->id);

        relationship_add_member($relationship1->id, $user1->id);
        relationship_add_member($relationship3->id, $user1->id);
        relationship_add_member($relationship1->id, $user3->id);
        relationship_add_member($relationship2->id, $user2->id);

        $list = relationship_get_visible_list($course1);
        $this->assertEquals(2, count($list));
        $this->assertNotEmpty($list[$relationship1->id]);
        $this->assertRegExp('/\(2\)$/', $list[$relationship1->id]);
        $this->assertNotEmpty($list[$relationship3->id]);
        $this->assertRegExp('/\(1\)$/', $list[$relationship3->id]);

        $list = relationship_get_visible_list($course1, false);
        $this->assertEquals(3, count($list));
        $this->assertNotEmpty($list[$relationship1->id]);
        $this->assertRegExp('/\(2\)$/', $list[$relationship1->id]);
        $this->assertNotEmpty($list[$relationship3->id]);
        $this->assertRegExp('/\(1\)$/', $list[$relationship3->id]);
        $this->assertNotEmpty($list[$relationship4->id]);
        $this->assertRegExp('/[^\)]$/', $list[$relationship4->id]);

        $list = relationship_get_visible_list($course2);
        $this->assertEquals(1, count($list));
        $this->assertNotEmpty($list[$relationship2->id]);
        $this->assertRegExp('/\(1\)$/', $list[$relationship2->id]);

        $list = relationship_get_visible_list($course2, false);
        $this->assertEquals(3, count($list));
        $this->assertNotEmpty($list[$relationship2->id]);
        $this->assertRegExp('/\(1\)$/', $list[$relationship2->id]);
        $this->assertNotEmpty($list[$relationship3->id]);
        $this->assertRegExp('/[^\)]$/', $list[$relationship3->id]);
        $this->assertNotEmpty($list[$relationship4->id]);
        $this->assertRegExp('/[^\)]$/', $list[$relationship4->id]);

        $list = relationship_get_visible_list($course3);
        $this->assertEquals(0, count($list));

        $list = relationship_get_visible_list($course3, false);
        $this->assertEquals(2, count($list));
        $this->assertNotEmpty($list[$relationship3->id]);
        $this->assertRegExp('/[^\)]$/', $list[$relationship3->id]);
        $this->assertNotEmpty($list[$relationship4->id]);
        $this->assertRegExp('/[^\)]$/', $list[$relationship4->id]);
    }

    public function test_relationship_get_relationships() {
        global $DB;

        $this->resetAfterTest();

        $category1 = $this->getDataGenerator()->create_category();
        $category2 = $this->getDataGenerator()->create_category();

        $relationship1 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category1->id)->id, 'name'=>'aaagrrryyy', 'idnumber'=>'','description'=>''));
        $relationship2 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category1->id)->id, 'name'=>'bbb', 'idnumber'=>'', 'description'=>'yyybrrr'));
        $relationship3 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_coursecat::instance($category1->id)->id, 'name'=>'ccc', 'idnumber'=>'xxarrrghyyy', 'description'=>'po_us'));
        $relationship4 = $this->getDataGenerator()->create_relationship(array('contextid'=>context_system::instance()->id));

        $result = relationship_get_relationships(context_coursecat::instance($category2->id)->id);
        $this->assertEquals(0, $result['totalrelationships']);
        $this->assertEquals(0, count($result['relationships']));
        $this->assertEquals(0, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id);
        $this->assertEquals(3, $result['totalrelationships']);
        $this->assertEquals(array($relationship1->id=>$relationship1, $relationship2->id=>$relationship2, $relationship3->id=>$relationship3), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 0, 100, 'arrrgh');
        $this->assertEquals(1, $result['totalrelationships']);
        $this->assertEquals(array($relationship3->id=>$relationship3), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 0, 100, 'brrr');
        $this->assertEquals(1, $result['totalrelationships']);
        $this->assertEquals(array($relationship2->id=>$relationship2), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 0, 100, 'grrr');
        $this->assertEquals(1, $result['totalrelationships']);
        $this->assertEquals(array($relationship1->id=>$relationship1), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 1, 1, 'yyy');
        $this->assertEquals(3, $result['totalrelationships']);
        $this->assertEquals(array($relationship2->id=>$relationship2), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 0, 100, 'po_us');
        $this->assertEquals(1, $result['totalrelationships']);
        $this->assertEquals(array($relationship3->id=>$relationship3), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);

        $result = relationship_get_relationships(context_coursecat::instance($category1->id)->id, 0, 100, 'pokus');
        $this->assertEquals(0, $result['totalrelationships']);
        $this->assertEquals(array(), $result['relationships']);
        $this->assertEquals(3, $result['allrelationships']);
    }
}
