<?php

class FrontendObjectTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $fixture_file = 'FrontendObjectTest.yml';

    protected $objectCreatorPage = null;

    protected $extraDataObjects = array(
        'FrontendObjectTestPage',
    );

    public function setUp() {
        parent::setUp();
    }

    //public function testBasic() {
    //    $response = $this->get('/create-page');
    //}

    public function testPageAuthorAndApprover() {
        if (!class_exists('WorkflowDefinition')) {
            return;
        }

        // Config
        $PAGE_TYPE = 'FrontendObjectTestPage';
        ObjectCreatorPage::config()->createable_types = array(
            $PAGE_TYPE,
        );

        $workflowImporter = singleton('WorkflowDefinitionImporter');
        // NOTE: Advaned Workflow 3.8+ 
        $this->assertTrue(method_exists($workflowImporter, 'importWorkflowDefinitionFromYAML'));
        // Import Workflow
        $workflowDef = $workflowImporter->importWorkflowDefinitionFromYAML(dirname(__FILE__).'/PageAuthorAndApprover.yml');
        $this->assertTrue($workflowDef && $workflowDef->exists());
        $workflowDef = WorkflowDefinition::get()->byID($workflowDef->ID);
        //Debug::dump($workflowDef->Actions()->count());
        //Debug::dump($workflowDef->Actions()->map('ID', 'ID')->toArray()); exit;

        // 
        $page = new ObjectCreatorPage;
        $page->Title = 'Test Creation Page';
        $page->URLSegment = 'create-page-workflow';
        $page->CreateType = $PAGE_TYPE;
        $page->CreateLocationID = $page->ID;
        $page->PublishOnCreate = false;
        $page->WorkflowDefinitionID = $workflowDef->ID;
        $page->ReviewWithPageTemplate = true;
        $page->AllowEditing = true;
        $page->SuccessMessage = '<p class="frontend-objects-created">Page created successfully.</p>';
        $page->EditingSuccessMessage = '<p class="frontend-objects-edited">Page edited successfully.</p>';

        $page->CanViewType = 'OnlyTheseUsers';
        $page->ViewerGroups()->add($this->objFromFixture('Group', 'creator'));
        $page->ViewerGroups()->add($this->objFromFixture('Group', 'approver'));

        $page->write();
        $page->publish('Stage', 'Live');

        // Ensure a non-logged in user cannot see the form
        $this->logInAs(0);
        $this->get('/'.$page->URLSegment);
        $this->assertEquals(0, count($this->cssParser()->getBySelector('#Form_CreateForm')));

        // Ensure a creatorMember can create objects on the form
        $this->logInAs('creatorMember');
        $this->get('/'.$page->URLSegment);
        $this->assertEquals(1, count($this->cssParser()->getBySelector('#Form_CreateForm')));

        // Submit and test that the page was created
        $this->submitForm('Form_CreateForm', 'action_createobject', array(
            'Title' => 'My new page',
            'Content' => '<p>The content on my page</p>',
        ));
        $createdPage = $PAGE_TYPE::get()->sort('ID')->last();
        $this->assertNotNull($createdPage);
        $this->assertEquals('My new page', $createdPage->Title);
        $this->assertEquals('<p>The content on my page</p>', $createdPage->Content);

        // Constants
        $EDIT_PAGE_URL = '/'.$page->URLSegment.'/edit/'.$createdPage->ID;
        $REVIEW_PAGE_URL = '/'.$page->URLSegment.'/review/'.$createdPage->ID;

        // Test to ensure 'creatorMember' user can no longer edit the page
        $this->get($EDIT_PAGE_URL);
        $this->assertEquals(0, count($this->cssParser()->getBySelector('#Form_CreateForm')));

        // 
        $this->logInAs('approverMember');

        // todo(Jake): Investigate why this gets a 'Page does not exist' error to ensure the 
        //             edit button doesn't have breakage.
        /*$this->get($REVIEW_PAGE_URL);
        $this->assertEquals(1, count($this->cssParser()->getBySelector('#FrontendWorkflowForm_Form2')));
        $response = $this->submitForm('FrontendWorkflowForm_Form2', 'action_doEdit', array(
            'ID' => $createdPage->ID,
        ));
        Debug::dump($response->getBody()); exit;*/

        $this->get($EDIT_PAGE_URL);
        $this->assertEquals(1, count($this->cssParser()->getBySelector('#Form_CreateForm')));

        $this->submitForm('Form_CreateForm', 'action_editobject', array(
            'Title' => 'My new page updated title',
            'Content' => '<p>The updated content on my page</p>',
        ));
        // Reload as the object should be updated
        $createdPage = $PAGE_TYPE::get()->byID($createdPage->ID);
        $this->assertNotNull($createdPage);
        $this->assertEquals('My new page updated title', $createdPage->Title);
        $this->assertEquals('<p>The updated content on my page</p>', $createdPage->Content);

        // Ensure saving/updating does not give 'creatorMember' access again / break workflow
        $this->logInAs('creatorMember');
        $this->get($EDIT_PAGE_URL);
        $this->assertEquals(0, count($this->cssParser()->getBySelector('#Form_CreateForm')));

        //
        $this->logInAs('approverMember');
        $response = $this->get($REVIEW_PAGE_URL);
        $this->assertEquals(1, count($this->cssParser()->getBySelector('#FrontendWorkflowForm_Form2')));

        // Get 'Approve' action name from HTML
        $actionName = ''; // ie.  action_transition_3
        $actionButtons = $this->cssParser()->getBySelector('.action');
        foreach ($actionButtons as $actionButton) {
            $attributes = $actionButton->attributes();
            $value = $attributes['value']->__toString();
            if ($value === 'Approve') {
                $actionName = $attributes['name'];
                break;
            }
        }
        $this->assertNotEquals('', $actionName);

        //Debug::dump($actionName);
        Debug::dump($response->getBody());

        // note: Silverstripe/3.5.3/create-page-workflow/review/Form/2
        $response = $this->submitForm('FrontendWorkflowForm_Form2', $actionName, array(
        ));

        Debug::dump($response->getBody()); exit;
    }
}