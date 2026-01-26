<?php
use PHPUnit\Framework\TestCase;

class FormHandlerTest extends TestCase {
    private $formHandler;

    protected function setUp() {
        $this->formHandler = new FormHandler();
    }

    protected function tearDown() {
        $this->formHandler->reset();
    }

    public function testValidateRequired() {
        $this->assertTrue($this->formHandler->validateRequired('name', 'John'));
        $this->assertFalse($this->formHandler->validateRequired('name', ''));
        $this->assertFalse($this->formHandler->validateRequired('name', '   '));
    }

    public function testValidateEmail() {
        $this->assertTrue($this->formHandler->validateEmail('email', 'test@example.com'));
        $this->assertFalse($this->formHandler->validateEmail('email', 'invalid-email'));
        $this->assertFalse($this->formHandler->validateEmail('email', ''));
    }

    public function testValidateMinLength() {
        $this->assertTrue($this->formHandler->validateMinLength('username', 'john', 3));
        $this->assertFalse($this->formHandler->validateMinLength('username', 'jo', 3));
    }

    public function testValidateMaxLength() {
        $this->assertTrue($this->formHandler->validateMaxLength('username', 'john', 10));
        $this->assertFalse($this->formHandler->validateMaxLength('username', 'verylongusername', 10));
    }

    public function testValidateNumeric() {
        $this->assertTrue($this->formHandler->validateNumeric('age', '25'));
        $this->assertTrue($this->formHandler->validateNumeric('age', 25));
        $this->assertFalse($this->formHandler->validateNumeric('age', 'notanumber'));
    }

    public function testValidatePassword() {
        $this->assertTrue($this->formHandler->validatePassword('password', 'StrongPass123!'));
        $this->assertFalse($this->formHandler->validatePassword('password', 'weak'));
    }

    public function testValidateMatch() {
        $this->assertTrue($this->formHandler->validateMatch('password', 'pass123', 'confirm', 'pass123'));
        $this->assertFalse($this->formHandler->validateMatch('password', 'pass123', 'confirm', 'pass456'));
    }

    public function testSanitizeInput() {
        $sanitized = $this->formHandler->sanitizeInput('name', '<b>John</b>');
        $this->assertEquals('<b>John</b>', $sanitized);
        $this->assertEquals('<b>John</b>', $this->formHandler->getData('name'));
    }

    public function testGetData() {
        $this->formHandler->sanitizeInput('name', 'John');
        $this->formHandler->sanitizeInput('email', 'john@example.com');

        $this->assertEquals('John', $this->formHandler->getData('name'));
        $this->assertEquals('john@example.com', $this->formHandler->getData('email'));
        $this->assertNull($this->formHandler->getData('nonexistent'));

        $allData = $this->formHandler->getData();
        $this->assertIsArray($allData);
        $this->assertEquals('John', $allData['name']);
    }

    public function testGetErrors() {
        $this->formHandler->validateRequired('name', '');
        $this->formHandler->validateEmail('email', 'invalid');

        $errors = $this->formHandler->getErrors();
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testIsValid() {
        $this->assertTrue($this->formHandler->isValid());

        $this->formHandler->validateRequired('name', '');
        $this->assertFalse($this->formHandler->isValid());
    }

    public function testGetFirstError() {
        $this->formHandler->validateRequired('name', '');
        $this->formHandler->validateEmail('email', 'invalid');

        $firstError = $this->formHandler->getFirstError();
        $this->assertIsString($firstError);
        $this->assertStringContains('required', $firstError);
    }

    public function testReset() {
        $this->formHandler->validateRequired('name', '');
        $this->formHandler->sanitizeInput('name', 'John');

        $this->assertFalse($this->formHandler->isValid());
        $this->assertNotEmpty($this->formHandler->getData());

        $this->formHandler->reset();

        $this->assertTrue($this->formHandler->isValid());
        $this->assertEmpty($this->formHandler->getData());
    }

    public function testProcessLogin() {
        // Valid login data
        $result = $this->formHandler->processLogin('john_doe', 'password123');
        $this->assertTrue($result);
        $this->assertEquals('john_doe', $this->formHandler->getData('username'));
        $this->assertEquals('password123', $this->formHandler->getData('password'));

        // Invalid login data
        $this->formHandler->reset();
        $result = $this->formHandler->processLogin('', '');
        $this->assertFalse($result);
        $this->assertArrayHasKey('username', $this->formHandler->getErrors());
        $this->assertArrayHasKey('password', $this->formHandler->getErrors());
    }

    public function testProcessStudentRegistration() {
        $validData = [
            'username' => 'john_doe',
            'password' => 'StrongPass123!',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'course' => 'Computer Science',
            'school' => 'Test University',
            'required_hours' => '200'
        ];

        $result = $this->formHandler->processStudentRegistration($validData);
        $this->assertTrue($result);

        // Invalid data
        $this->formHandler->reset();
        $invalidData = [
            'username' => '',
            'password' => 'weak',
            'first_name' => '',
            'last_name' => '',
            'course' => '',
            'school' => '',
            'required_hours' => 'notanumber'
        ];

        $result = $this->formHandler->processStudentRegistration($invalidData);
        $this->assertFalse($result);
        $this->assertNotEmpty($this->formHandler->getErrors());
    }
}
?>
