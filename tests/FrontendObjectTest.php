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
        new FrontendObjectTestPage;
        $PAGE_TYPE = 'FrontendObjectTestPage';
        ObjectCreatorPage::config()->createable_types = array(
            $PAGE_TYPE,
        );

        // Import Workflow
        $workflowDef = $this->importDefinition(dirname(__FILE__).'/PageAuthorAndApprover.yml');
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
        $page->SuccessMessage = '<p class="frontend-objects-created">Page created successfully.</p>';
        $page->EditingSuccessMessage = '<p class="frontend-objects-edited">Page edited successfully.</p>';

        $page->CanViewType = 'OnlyTheseUsers';
        $page->ViewerGroups()->add($this->objFromFixture('Group', 'creator'));
        $page->ViewerGroups()->add($this->objFromFixture('Group', 'approver'));

        $page->write();
        $page->publish('Stage', 'Live');

        //
        $this->logInAs('creatorMember');

        //
        $this->get('/'.$page->URLSegment);
        $form = $this->cssParser()->getBySelector('#Form_CreateForm');
        $this->assertEquals(1, count($form));

        // Submit and test that the page was created
        $this->submitForm('Form_CreateForm', 'action_createobject', array(
            'Title' => 'My new page',
            'Content' => '<p>The content on my page</p>',
        ));
        $createdPage = $PAGE_TYPE::get()->first();
        $this->assertNotNull($createdPage);
        $this->assertEquals('My new page', $createdPage->Title);
        $this->assertEquals('<p>The content on my page</p>', $createdPage->Content);

        // Test to ensure user can no longer edit the page
        $response = $this->get('/'.$page->URLSegment.'/edit/'.$createdPage->ID);
        $html = $response->getBody();
        $this->assertTrue(strpos($html, 'This item is not editable') !== FALSE);

        // 
        $this->logInAs('approverMember');
        $response = $this->get('/'.$page->URLSegment.'/edit/'.$createdPage->ID);
        $html = $response->getBody();
        //Debug::dump($html); exit;
    }

    // todo(Jake): Fix bug where 'importDefinition' doesn't work during test. WorkflowAction items
    //             get written to a WorkflowDefID of 0. 
    //
    //             Solution: Improve AdvancedWorkflow to allow importing an exported definition via
    //                       code rather than a hack.
    //
    private function importDefinition($filepath) {
        //$yml = singleton('WorkflowDefinitionImporter')->parseYAMLImport($filepath);

        $workflowBulkLoader = new WorkflowBulkLoader('WorkflowDefinition');

        $method = new ReflectionMethod($workflowBulkLoader, 'processAll');
        $method->setAccessible(true);
        /** @var BulkLoader_Result $bulkLoaderResults **/
        $bulkLoaderResults = $method->invoke($workflowBulkLoader, $filepath);

        $createdItemSet = $bulkLoaderResults->Created()->toArray();
        $createdItem = reset($createdItemSet);
        return $createdItem;
    }

    /*private function assertMatchCountBySelector($selector, $expectedCount) {
        if(is_string($expectedMatches)) $expectedMatches = array($expectedMatches);

        $items = $this->cssParser()->getBySelector($selector);
        $itemsCount = count($items);

        $this->assertTrue(
            $expectedMatches == $actuals,
                "Failed asserting the CSS selector '$selector' returns $expectedCount:\n'"
                . implode("'\n'", $expectedMatches) . "'\n\n"
                . "Instead $itemsCount results were found.\n'"
        );

        return count($items);
    }*/
}