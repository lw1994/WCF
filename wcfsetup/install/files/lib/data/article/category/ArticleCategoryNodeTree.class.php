<?php
declare(strict_types=1);
namespace wcf\data\article\category;
use wcf\data\category\CategoryNode;
use wcf\data\category\CategoryNodeTree;

/**
 * Represents a list of article category nodes.
 *
 * @author	Marcel Werk
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Data\Article\Category
 * @since	3.0
 */
class ArticleCategoryNodeTree extends CategoryNodeTree {
	/**
	 * name of the category node class
	 * @var	string
	 */
	protected $nodeClassName = ArticleCategoryNode::class;
	
	/**
	 * @inheritDoc
	 */
	public function isIncluded(CategoryNode $categoryNode) {
		/** @var ArticleCategoryNode $categoryNode */
		
		return parent::isIncluded($categoryNode) && $categoryNode->isAccessible();
	}
}