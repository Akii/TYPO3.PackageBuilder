<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2009 Ingmar Schlecht
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
namespace TYPO3\PackageBuilder\Domain\Model\DomainObject;

/**
 * @package PackageBuilder
 * @license http://www.gnu.org/licenses/gpl.html GNU General Public License, version 3 or later
 */
class DateTimeProperty extends AbstractProperty {

	public function getTypeForComment() {
		return 'DateTime';
	}

	public function getTypeHint() {
		return 'DateTime';
	}

	public function getSqlDefinition() {
		return $this->getFieldName() . ' int(11) DEFAULT \'0\' NOT NULL,';
	}

	public function getNameToBeDisplayedInFluidTemplate() {
		return $this->name . ' -> f:format.date()';
	}

}

