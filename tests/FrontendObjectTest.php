<?php

class FrontendObjectTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $fixture_file = 'FrontendObjectTest.yml';

    protected $objectCreatorPage = null;

    protected $extraDataObjects = array(
        // Advanced Workflow
        //'WorkflowDefinition',
        //'WorkflowAction',
        //'WorkflowInstance',
        //'WorkflowTransition',
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
        ObjectCreatorPage::config()->createable_types = array(
            'Page',
        );

        // Import Workflow
        $workflowDef = $this->importDefinition(dirname(__FILE__).'/PageAuthorAndApprover.yml');

        //
        $page = new ObjectCreatorPage;
        $page->Title = 'Test Creation Page';
        $page->URLSegment = 'create-page-workflow';
        $page->CreateType = 'Page';
        $page->CreateLocationID = $page->ID;
        $page->WorkflowDefinitionID = $workflowDef->ID;
        $page->ReviewWithPageTemplate = true;
        $page->write();
        $page->publish('Stage', 'Live');

        //
        $response = $this->get('/'.$page->URLSegment);
        
        Debug::dump($response); exit;
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

    private function importDefinition($filepath) {
        //$filepath = dirname(__FILE__).'/WorkflowDefinition.yml';
        $record = singleton('WorkflowDefinitionImporter')->parseYAMLImport($filepath);
        $struct = $record['Injector']['ExportedWorkflow'];
        $template = Injector::inst()->createWithArgs('WorkflowTemplate', $struct['constructor']);
        $template->setStructure($struct['properties']['structure']);

        $def = new WorkflowDefinition;
        $def->workflowService = singleton('WorkflowService');
        $def->Template = $template->getName();
        // NOTE(Jake): Required to avoid DB::query() error during unit test
        $def->Sort = 1;
        $def->write();
        return $def;
    }

    /*public function testModelAdmin()
    {
        $this->logInAs('admin');

        // Test ModelAdmin listing
        $controller = singleton('SilbinaryWolf\SteamedClams\ClamAVAdmin');
        $response = $this->get($controller->Link());
    }

    public function testClamAVReport()
    {
        if (!class_exists('SS_Report')) {
            return;
        }
        $this->logInAs('admin');

        // Test Report page
        $controller = singleton('ClamAVScanReport');
        $response = $this->get($controller->getLink());
    }*/
}