<?php
namespace TYPO3\PackageBuilder\Tests\Functional;
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2012 Nico de Haen
 *  All rights reserved
 *
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/


/**
 *
 * @author Nico de Haen
 *
 */

use TYPO3\Flow\Annotations as Flow;


class CodeGeneratorTest extends \TYPO3\PackageBuilder\Tests\FunctionalBaseTest {


	function setUp() {
		parent::setUp();
	}


	/**
	 * Generate the appropriate code for a simple model class
	 * for a non aggregate root domain object with one boolean property
	 *
	 * @test
	 */
	function generateCodeForModelClassWithBooleanProperty() {
		$modelName = 'ModelCgt1';
		$propertyName = 'blue';
		$domainObject = $this->buildDomainObject($modelName);
		$property = new \TYPO3\PackageBuilder\Domain\Model\DomainObject\BooleanProperty();
		$property->setName($propertyName);
		$property->setRequired(TRUE);
		$domainObject->addProperty($property);
		$classFileContent = $this->codeGenerator->generateDomainObjectCode($domainObject, FALSE);
		$this->assertRegExp("/.*class ModelCgt1.*/", $classFileContent, 'Class declaration was not generated');
		$this->assertRegExp('/.*protected \\$blue.*/', $classFileContent, 'boolean property was not generated');
		$this->assertRegExp('/.*\* \@var boolean.*/', $classFileContent, 'var tag for boolean property was not generated');
		$this->assertRegExp('/.*\* \@validate NotEmpty.*/', $classFileContent, 'validate tag for required property was not generated');
		$this->assertRegExp('/.*public function getBlue\(\).*/', $classFileContent, 'Getter for boolean property was not generated');
		$this->assertRegExp('/.*public function setBlue\(\$blue\).*/', $classFileContent, 'Setter for boolean property was not generated');
		$this->assertRegExp('/.*public function isBlue\(\).*/', $classFileContent, 'is method for boolean property was not generated');
	}

	/**
	 * Write a simple model class for a non aggregate root domain object with one string property
	 *
	 * @test
	 */
	function writeModelClassWithStringProperty(){
		$modelName = 'ModelCgt2';
		$propertyName = 'title';
		$domainObject = $this->buildDomainObject($modelName);
		$property = new \TYPO3\PackageBuilder\Domain\Model\DomainObject\StringProperty($propertyName);
		//$property->setRequired(TRUE);
		$domainObject->addProperty($property);
		$classFileContent = $this->codeGenerator->generateDomainObjectCode($domainObject,$this->extension);

		$modelClassDir =  'Classes/Domain/Model/';
		\TYPO3\Flow\Utility\Files::createDirectoryRecursively($this->extension->getExtensionDir() . $modelClassDir);
		$absModelClassDir = $this->extension->getExtensionDir().$modelClassDir;
		$this->assertTrue(is_dir($absModelClassDir),'Directory ' . $absModelClassDir . ' was not created');

		$modelClassPath =  $absModelClassDir . $domainObject->getName() . '.php';
		file_put_contents($modelClassPath,$classFileContent);
		$this->assertFileExists($modelClassPath,'File was not generated: ' . $modelClassPath);
		$className = $this->extension->getNameSpace() .  $domainObject->getName();
		if(!class_exists($className)) {
			include($modelClassPath);
		}
		$this->assertTrue(class_exists($className),'Class was not generated:'.$className);

		$reflection = new ReflectionClass($className);
		$this->assertTrue($reflection->hasMethod('get' . ucfirst($propertyName)),'Getter was not generated');
		$this->assertTrue($reflection->hasMethod('set' . ucfirst($propertyName)),'Setter was not generated');
		$this->assertFalse($reflection->hasMethod('is' . ucfirst($propertyName)),'isMethod should not be generated');
		$setterMethod = $reflection->getMethod('set' . ucfirst($propertyName));
		$parameters = $setterMethod->getParameters();
		$this->assertEquals(1, count($parameters),'Wrong parameter count in setter method');
		$parameter = current($parameters);
		$this->assertEquals($parameter->getName(), $propertyName,'Wrong parameter name in setter method');

	}

}

?>
