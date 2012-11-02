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


class RoundtripServiceTest extends \TYPO3\PackageBuilder\Tests\FunctionalBaseTest {


	/**
	 * @var \TYPO3\PackageBuilder\Service\TYPO3\RoundTrip
	 */
	protected $roundTripService;

	function setUp() {
		parent::setUp();
		$this->roundTripService =  $this->getMock($this->buildAccessibleProxy('\\TYPO3\\PackageBuilder\\Service\\TYPO3\\RoundTrip'), array('dummy'));
		$this->roundTripService->_set('logger', new \TYPO3\Flow\Log\Logger());
		$this->roundTripService->_set('classParser', new \TYPO3\ParserApi\Service\Parser());
	}


	/**
	 * Write a simple model class for a non aggregate root domain obbject
	 * @test
	 */
	function relatedMethodsReflectRenamingAProperty() {
		$modelName = 'Model7';
		$this->generateInitialModelClassFile($modelName);
		// create an "old" domainObject
		$domainObject = $this->buildDomainObject($modelName);
		$this->assertTrue(is_object($domainObject), 'No domain object');

		$property = new \TYPO3\PackageBuilder\Domain\Model\DomainObject\StringProperty('prop1');
		$uniqueIdentifier1 = md5(microtime() . 'prop1');
		$property->setUniqueIdentifier($uniqueIdentifier1);
		$domainObject->addProperty($property);
		$uniqueIdentifier2 = md5(microtime() . 'model');
		$domainObject->setUniqueIdentifier($uniqueIdentifier2);

		$this->roundTripService->_set('oldDomainObjects', array($domainObject->getUniqueIdentifier() => $domainObject));

		// create an "old" class object.
		$modelClassObject = $this->classBuilder->generateModelClassObject($domainObject, FALSE);
		$this->assertTrue(is_object($modelClassObject), 'No class object');

		// Check that the getter/methods exist
		$this->assertTrue($modelClassObject->methodExists('getProp1'));
		$this->assertTrue($modelClassObject->methodExists('setProp1'));

		// set the class object manually, this is usually parsed from an existing class file
		$this->roundTripService->_set('classObject', $modelClassObject);

		// build a new domain object with the same unique identifiers
		$newDomainObject = $this->buildDomainObject('Dummy');
		$property = new \TYPO3\PackageBuilder\Domain\Model\DomainObject\BooleanProperty('newProp1Name');
		$property->setUniqueIdentifier($uniqueIdentifier1);
		$property->setRequired(TRUE);
		$newDomainObject->addProperty($property);
		$newDomainObject->setUniqueIdentifier($uniqueIdentifier2);

		// now the slass object should be updated
		$this->roundTripService->_call('updateModelClassProperties', $domainObject, $newDomainObject);

		$classObject = $this->roundTripService->_get('classObject');
		$this->assertTrue($classObject->methodExists('getNewProp1Name'));
		$this->assertTrue($classObject->methodExists('setNewProp1Name'));
	}

	/**
	 * @test
	 */
	public function roundTripFindsExistingClass() {
		$modelName = 'Model8';
		$domainObject = $this->buildDomainObject($modelName);
		$this->generateInitialModelClassFile($modelName);
		$property = new \TYPO3\PackageBuilder\Domain\Model\DomainObject\StringProperty('prop1');
		$uniqueIdentifier1 = md5(microtime() . 'prop1');
		$property->setUniqueIdentifier($uniqueIdentifier1);
		$domainObject->addProperty($property);
		$uniqueIdentifier2 = md5(microtime() . 'model');
		$domainObject->setUniqueIdentifier($uniqueIdentifier2);

		$this->roundTripService->_set('oldDomainObjects', array($domainObject->getUniqueIdentifier() => $domainObject));
		$this->roundTripService->_set('previousExtensionDirectory', $this->testDir);
		$classObject = $this->roundTripService->getDomainModelClass($domainObject);
		$this->assertEquals('DUMMY\\Dummy\\Domain\\Model\\Model8', $classObject->getName());

	}

	/**
	 * Builds an initial class file to test parsing and modifiying of existing classes
	 *
	 * This class file is generated based on the CodeTemplates
	 * @param string $modelName
	 */
	function generateInitialModelClassFile($modelName){
		$domainObject = $this->buildDomainObject($modelName);
		$classFileContent = $this->codeGenerator->generateDomainObjectCode($domainObject, $this->extension);

		$modelClassDir =  'Classes/Domain/Model/';
		\TYPO3\Flow\Utility\Files::createDirectoryRecursively($this->extension->getExtensionDir() . $modelClassDir);
		$absModelClassDir = $this->extension->getExtensionDir().$modelClassDir;
		$this->assertTrue(is_dir($absModelClassDir),'Directory ' . $absModelClassDir . ' was not created');

		$modelClassPath =  $absModelClassDir . $domainObject->getName() . '.php';
		file_put_contents($modelClassPath,$classFileContent);
		$this->assertFileExists($modelClassPath,'File was not generated: ' . $modelClassPath);
	}

	function removeInitialModelClassFile($modelName){
		if(@file_exists($this->extension->getExtensionDir().$this->modelClassDir.$modelName . '.php')){
			unlink($this->extension->getExtensionDir().$this->modelClassDir.$modelName . '.php');
		}
	}

}
?>