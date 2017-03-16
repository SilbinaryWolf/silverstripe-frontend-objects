<?php

class FrontendObjectTest extends FunctionalTest
{
    protected $usesDatabase = true;

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

        // Import Workflow
        $workflowDef = $this->importDefinition(dirname(__FILE__).'/PageAuthorAndApprover.yml');
        $this->assertTrue($workflowDef && $workflowDef->exists());

        // 
        $page = new ObjectCreatorPage;
        $page->Title = 'Test Creation Page';
        $page->URLSegment = 'create-page-workflow';
        $page->CreateType = $PAGE_TYPE;
        $page->CreateLocationID = $page->ID;
        $page->WorkflowDefinitionID = $workflowDef->ID;
        $page->ReviewWithPageTemplate = true;
        $page->SuccessMessage = '<p class="frontend-objects-created">Page created successfully.</p>';
        $page->EditingSuccessMessage = '<p class="frontend-objects-edited">Page edited successfully.</p>';

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

        // Submit
        $this->submitForm('Form_CreateForm', 'action_createobject', array(
            'Title' => 'My new page',
            'Content' => '<p>The content on my page</p>',
        ));

        /*$response = $this->post(
            '/'.$page->URLSegment.'/CreateForm',
            array(
                'Title' => 'My new page',
                'Content' => '<p>The content on my page</p>',
                'action_createobject' => 'Create',
            )
        );*/
        
        //Debug::dump($response); exit;
    }

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

        // todo(Jake): remove
        //$filepath = dirname(__FILE__).'/WorkflowDefinition.yml';
        /*$record = singleton('WorkflowDefinitionImporter')->parseYAMLImport($filepath);
        $struct = $record['Injector']['ExportedWorkflow'];
        $template = Injector::inst()->createWithArgs('WorkflowTemplate', $struct['constructor']);
        $template->setStructure($struct['properties']['structure']);

        $def = new WorkflowDefinition;
        $def->workflowService = singleton('WorkflowService');
        $def->Template = $template->getName();
        // NOTE(Jake): Required to avoid DB::query() error during unit test
        //$def->Sort = 1;
        singleton('WorkflowService')->defineFromTemplate($def, $def->Template);
        //$def->write();
        return $def;*/
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