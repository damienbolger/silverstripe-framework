<?php

use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Core\Convert;
use SilverStripe\Dev\CSSContentParser;
use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Control\Controller;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridField;





class GridFieldAddExistingAutocompleterTest extends FunctionalTest {

	protected static $fixture_file = 'GridFieldTest.yml';

	protected $extraDataObjects = array('GridFieldTest_Team', 'GridFieldTest_Player', 'GridFieldTest_Cheerleader');

	function testScaffoldSearchFields() {
		$autoCompleter = new GridFieldAddExistingAutocompleter($targetFragment = 'before', array('Test'));
		$gridFieldTest_Team = GridFieldTest_Team::singleton();
		$this->assertEquals(
			$autoCompleter->scaffoldSearchFields('GridFieldTest_Team'),
			array(
				'Name:PartialMatch',
				'City:StartsWith',
				'Cheerleaders.Name:StartsWith'
			)
		);
		$this->assertEquals(
			$autoCompleter->scaffoldSearchFields('GridFieldTest_Cheerleader'),
			array(
				'Name:StartsWith'
			)
		);
	}

	function testSearch() {
		$team1 = $this->objFromFixture('GridFieldTest_Team', 'team1');
		$team2 = $this->objFromFixture('GridFieldTest_Team', 'team2');

		$response = $this->get('GridFieldAddExistingAutocompleterTest_Controller');
		$this->assertFalse($response->isError());
		$parser = new CSSContentParser($response->getBody());
		$btns = $parser->getBySelector('.grid-field .action_gridfield_relationfind');

		$response = $this->post(
			'GridFieldAddExistingAutocompleterTest_Controller/Form/field/testfield/search'
				. '/?gridfield_relationsearch=Team 2',
			array((string)$btns[0]['name'] => 1)
		);
		$this->assertFalse($response->isError());
		$result = Convert::json2array($response->getBody());
		$this->assertEquals(1, count($result));
		$this->assertEquals(array(array(
			'label' => 'Team 2',
			'value' => 'Team 2',
			'id' => $team2->ID,
		)), $result);

		$response = $this->post(
			'GridFieldAddExistingAutocompleterTest_Controller/Form/field/testfield/'
				. 'search/?gridfield_relationsearch=Heather',
			array((string)$btns[0]['name'] => 1)
		);
		$this->assertFalse($response->isError());
		$result = Convert::json2array($response->getBody());
		$this->assertEquals(1, count($result), "The relational filter did not work");

		$response = $this->post(
			'GridFieldAddExistingAutocompleterTest_Controller/Form/field/testfield/search'
				. '/?gridfield_relationsearch=Unknown',
			array((string)$btns[0]['name'] => 1)
		);
		$this->assertFalse($response->isError());
		$result = Convert::json2array($response->getBody());
		$this->assertEmpty($result, 'The output is either an empty array or boolean FALSE');
	}

	public function testAdd() {
		$this->logInWithPermission('ADMIN');
		$team1 = $this->objFromFixture('GridFieldTest_Team', 'team1');
		$team2 = $this->objFromFixture('GridFieldTest_Team', 'team2');

		$response = $this->get('GridFieldAddExistingAutocompleterTest_Controller');
		$this->assertFalse($response->isError());
		$parser = new CSSContentParser($response->getBody());
		$items = $parser->getBySelector('.grid-field .ss-gridfield-items .ss-gridfield-item');
		$this->assertEquals(1, count($items));
		$this->assertEquals($team1->ID, (int)$items[0]['data-id']);

		$btns = $parser->getBySelector('.grid-field .action_gridfield_relationadd');
		$response = $this->post(
			'GridFieldAddExistingAutocompleterTest_Controller/Form/field/testfield',
			array(
				'relationID' => $team2->ID,
				(string)$btns[0]['name'] => 1
			)
		);
		$this->assertFalse($response->isError());
		$parser = new CSSContentParser($response->getBody());
		$items = $parser->getBySelector('.grid-field .ss-gridfield-items .ss-gridfield-item');
		$this->assertEquals(2, count($items));
		$this->assertDOSEquals(array(
			array('ID' => (int)$items[0]['data-id']),
			array('ID' => (int)$items[1]['data-id']),
		), new ArrayList(array($team1, $team2)));

	}

}

/**
 * @skipUpgrade
 */
class GridFieldAddExistingAutocompleterTest_Controller extends Controller implements TestOnly {

	private static $allowed_actions = array('Form');

	protected $template = 'BlankPage';

	public function Form() {
		$player = GridFieldTest_Player::get()->find('Email', 'player1@test.com');
		$config = GridFieldConfig::create()->addComponents(
			$relationComponent = new GridFieldAddExistingAutocompleter('before'),
			new GridFieldDataColumns()
		);
		$field = new GridField('testfield', 'testfield', $player->Teams(), $config);
		return new Form($this, 'Form', new FieldList($field), new FieldList());
	}
}
