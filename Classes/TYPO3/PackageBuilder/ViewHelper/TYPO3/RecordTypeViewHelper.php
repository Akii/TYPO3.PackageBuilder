<?php
namespace TYPO3\PackageBuilder\ViewHelper\TYPO3;
/***************************************************************
 *  Copyright notice
 *
 *  All rights reserved
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

class RecordTypeViewHelper extends \TYPO3\Fluid\Core\ViewHelper\AbstractViewHelper {

	/**
	 * @var \TYPO3\PackageBuilder\Configuration\AbstractConfigurationManager
	 */
	protected $packageConfigurationManager;


	/**
	 * Helper function to find the parents class recordType
	 * @param \TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject
	 * @return string
	 */
	public function render(\TYPO3\PackageBuilder\Domain\Model\DomainObject $domainObject) {
		$classSettings = $this->packageConfigurationManager->getExtbaseClassConfiguration($domainObject->getParentClass());
		if (isset($classSettings['recordType'])) {
			$parentRecordType = $classSettings['recordType'];
		} else {
			$parentRecordType = str_replace('Domain_Model_', '', $domainObject->getParentClass());
			if (!isset($TCA[$domainObject->getDatabaseTableName()]['types'][$parentRecordType])) {
				$parentRecordType = 1;
			}
		}

		$this->templateVariableContainer->add('parentRecordType', $parentRecordType);
		$content = $this->renderChildren();
		$this->templateVariableContainer->remove('parentRecordType');

		return $content;
	}

}

?>