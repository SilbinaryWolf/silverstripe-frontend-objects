<?php

// todo(Jake): Add 'implements TestOnly'
class FrontendObjectTestPage extends Page implements TestOnly
{
    public function getFrontendCreateFields() {
        $cmsFields = $this->getCMSFields();
        $cmsFieldsWhitelist = array(
            'Title',
            'Content'
        );

        $fields = new FieldList;
        foreach ($cmsFieldsWhitelist as $fieldName) {
            $field = $fields->dataFieldByName($fieldName);
            if (!$field) {
                continue;
            }
            $fields->push($field);
        }

        $this->owner->extend('updateFrontendCreateFields', $fields);
        return $fields;
    }
}