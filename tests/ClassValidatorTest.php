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

    public function testValidate() {
        $this->validator->validate($this->loadFromFile('class1.php'));
    }

    public function testNonWhitelistedClass() {
        $this->expectException(\PHPSandbox\Error::class); 
        $this->validator->validate($this->loadFromFile('class2.php'));
    }

}