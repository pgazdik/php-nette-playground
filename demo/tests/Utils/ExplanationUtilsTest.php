<?php
namespace Tests\Utils;

use App\Utils\ExplanationUtils;
use PHPUnit\Framework\TestCase;

class ExplanationUtilsTest extends TestCase
{
    public function testGetStatusExplanations()
    {
        $explanations = ExplanationUtils::getStatusExplanations();
        
        $this->assertIsArray($explanations);
        $this->assertNotEmpty($explanations);
        
        foreach ($explanations as $explanation) {
            $this->assertIsObject($explanation);
            $this->assertObjectHasProperty('name', $explanation);
            $this->assertObjectHasProperty('color', $explanation);
            $this->assertObjectHasProperty('description', $explanation);
            
            $this->assertIsString($explanation->name);
            $this->assertIsString($explanation->color);
            $this->assertIsString($explanation->description);
        }
    }
}
