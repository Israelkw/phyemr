<?php

// In a real environment, this would be: require_once __DIR__ . '/../vendor/autoload.php';
// And the class would extend PHPUnit\Framework\TestCase
require_once __DIR__ . '/../includes/Validator.php';

class ValidatorTest {
    private $testResults = ['passed' => 0, 'failed' => 0, 'details' => []];

    private function assertTrue($condition, $message) {
        if ($condition) {
            $this->testResults['passed']++;
            $this->testResults['details'][] = ['status' => 'PASSED', 'message' => $message];
        } else {
            $this->testResults['failed']++;
            $this->testResults['details'][] = ['status' => 'FAILED', 'message' => $message];
            // echo "Assertion failed: $message\n"; // Direct echo can be noisy
        }
    }

    private function assertFalse($condition, $message) {
        $this->assertTrue(!$condition, $message);
    }

    private function assertEquals($expected, $actual, $message) {
        $this->assertTrue($expected == $actual, "$message (Expected: $expected, Actual: $actual)");
    }

    private function assertCount($expectedCount, $array, $message) {
        $this->assertTrue(count($array) == $expectedCount, "$message (Expected count: $expectedCount, Actual: " . count($array) . ")");
    }

    public function runAllTests() {
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (strpos($method, 'test') === 0) {
                // Reset state for each test if necessary (not strictly needed for Validator if stateless)
                $this->testRequiredRule(); // Example direct call, ideally use reflection or call specific tests
                $this->testEmailRule();
                $this->testMinLengthRule();
                $this->testDateRule();
                $this->testValidationSuccess();
                $this->testValidationFailure();
                break; // Only run tests once via this loop structure for now.
                       // A real test runner would invoke each method.
            }
        }
        $this->printResults();
    }

    private function printResults() {
        echo "ValidatorTest Results:\n";
        echo "Passed: {$this->testResults['passed']}, Failed: {$this->testResults['failed']}\n";
        echo "Details:\n";
        foreach ($this->testResults['details'] as $detail) {
            echo "- {$detail['status']}: {$detail['message']}\n";
        }
        echo "--------------------------------------------------\n";
    }

    public function testRequiredRule() {
        $validator1 = new Validator(['name' => '']);
        $validator1->addField('name', 'required');
        $this->assertFalse($validator1->validate(), "Required rule should fail for empty string.");

        $validator2 = new Validator(['name' => '  ']); // Whitespace
        $validator2->addField('name', 'required');
        $this->assertFalse($validator2->validate(), "Required rule should fail for whitespace string.");

        $validator3 = new Validator(['name' => 'John Doe']);
        $validator3->addField('name', 'required');
        $this->assertTrue($validator3->validate(), "Required rule should pass for non-empty string.");

        $validator4 = new Validator([]); // Field not present
        $validator4->addField('name', 'required');
        $this->assertFalse($validator4->validate(), "Required rule should fail for missing field.");
    }

    public function testEmailRule() {
        $validator1 = new Validator(['email' => 'test@example.com']);
        $validator1->addField('email', 'email');
        $this->assertTrue($validator1->validate(), "Email rule should pass for valid email.");

        $validator2 = new Validator(['email' => 'invalid-email']);
        $validator2->addField('email', 'email');
        $this->assertFalse($validator2->validate(), "Email rule should fail for invalid email.");

        $validator3 = new Validator(['email' => '']); // Optional: empty string for non-required email
        $validator3->addField('email', 'email'); // Assumes email rule allows empty if not 'required'
        $this->assertTrue($validator3->validate(), "Email rule should pass for empty string if not required.");
    }

    public function testMinLengthRule() {
        $validator1 = new Validator(['text' => 'abc']);
        $validator1->addField('text', 'minLength:5');
        $this->assertFalse($validator1->validate(), "MinLength rule should fail for 'abc' with minLength:5.");

        $validator2 = new Validator(['text' => 'abcdef']);
        $validator2->addField('text', 'minLength:5');
        $this->assertTrue($validator2->validate(), "MinLength rule should pass for 'abcdef' with minLength:5.");
    }

    public function testDateRule() {
        $validator1 = new Validator(['event_date' => '2023-10-26']);
        $validator1->addField('event_date', 'date:Y-m-d');
        $this->assertTrue($validator1->validate(), "Date rule should pass for '2023-10-26' with Y-m-d format.");

        $validator2 = new Validator(['event_date' => '26/10/2023']);
        $validator2->addField('event_date', 'date:Y-m-d');
        $this->assertFalse($validator2->validate(), "Date rule should fail for '26/10/2023' with Y-m-d format.");

        $validator3 = new Validator(['event_date' => '2023-10-26']);
        $validator3->addField('event_date', 'date'); // Test default Y-m-d format
        $this->assertTrue($validator3->validate(), "Date rule should pass for '2023-10-26' with default format.");
    }

    public function testValidationSuccess() {
        $data = ['name' => 'John Doe', 'email' => 'john.doe@example.com', 'age' => '30'];
        $validator = new Validator($data);
        $validator->addField('name', 'required|minLength:3');
        $validator->addField('email', 'required|email');
        $validator->addField('age', 'numeric');

        $this->assertTrue($validator->validate(), "Validation should pass for all valid fields.");
        $this->assertCount(0, $validator->getErrors(), "There should be no errors when validation passes.");
    }

    public function testValidationFailure() {
        $data = ['name' => 'J', 'email' => 'invalid', 'age' => 'thirty'];
        $validator = new Validator($data);
        $validator->addField('name', 'required|minLength:3');
        $validator->addField('email', 'required|email');
        $validator->addField('age', 'numeric');

        $this->assertFalse($validator->validate(), "Validation should fail for invalid fields.");
        $errors = $validator->getErrors();
        $this->assertCount(3, $errors, "There should be 3 fields with errors.");
        $this->assertTrue(isset($errors['name']), "Error for 'name' field should exist.");
        $this->assertTrue(isset($errors['email']), "Error for 'email' field should exist.");
        $this->assertTrue(isset($errors['age']), "Error for 'age' field should exist.");

        $firstError = $validator->getFirstError();
        // This depends on the order of fields and rules
        $this->assertTrue($firstError !== null, "getFirstError should return an error message.");
        // Example: $this->assertEquals("The name field must be at least 3 characters long.", $validator->getFirstError('name'), "Checking specific error for name.");
    }
}

// To run these tests (simulated):
// $validatorTest = new ValidatorTest();
// $validatorTest->runAllTests(); // This will call all 'test*' methods defined above.

?>
