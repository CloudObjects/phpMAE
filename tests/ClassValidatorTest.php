<?php

namespace CloudObjects\PhpMAE;

class ClassValidatorTest extends \PHPUnit_Framework_TestCase {

    private $validator;

    private function loadFromFile($filename) {
        return file_get_contents(__DIR__.'/fixtures/'.$filename);
    }

    protected function setUp() {
        $this->validator = new ClassValidator;
    }

    public function testValidateAsController() {
        $this->validator->validateAsController($this->loadFromFile('controller1.php'));
        try {
            $this->validator->validateAsController($this->loadFromFile('provider1.php'));
            $this->assertTrue(false);
        } catch (\Exception $e) { }
    }

    public function testValidateAsProvider() {
        $this->validator->validateAsProvider($this->loadFromFile('provider1.php'));
        try {
            $this->validator->validateAsProvider($this->loadFromFile('controller1.php'));
            $this->assertTrue(false);
        } catch (\Exception $e) { }
    }

}