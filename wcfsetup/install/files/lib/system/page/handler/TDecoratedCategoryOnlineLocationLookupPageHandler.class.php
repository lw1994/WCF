<?php
namespace wcf\system\page\handler;

/**
 * Implementation of the `IOnlineLocationPageHandler` and `ILookupPageHandler` interfaces
 * and implementing the `IMenuPageHandler::isVisible()` method..
 *
 * @author	Matthias Schmidt
 * @copyright	2001-2016 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\System\Page\Handler
 * @since	3.0
 */
trait TDecoratedCategoryOnlineLocationLookupPageHandler {
	use TDecoratedCategoryOnlineLocationPageHandler;
	use TDecoratedCategoryLookupPageHandler;
	use TDecoratedCategoryMenuPageHandler;
}
