<?php
namespace wcf\data\article;
use wcf\data\article\category\ArticleCategory;
use wcf\data\article\content\ArticleContent;
use wcf\data\article\content\ViewableArticleContent;
use wcf\data\media\ViewableMedia;
use wcf\data\user\User;
use wcf\data\user\UserProfile;
use wcf\data\DatabaseObjectDecorator;
use wcf\system\cache\runtime\UserProfileRuntimeCache;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\user\storage\UserStorageHandler;
use wcf\system\visitTracker\VisitTracker;
use wcf\system\WCF;

/**
 * Represents a viewable article.
 *
 * @author	Marcel Werk
 * @copyright	2001-2017 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	WoltLabSuite\Core\Data\Article
 * @since	3.0
 *
 * @method	        Article					getDecoratedObject()
 * @method	        ArticleContent|ViewableArticleContent	getArticleContent()
 * @mixin	        Article
 * @property-read 	integer|null	                        $visitTime	last time the active user has visited the time or `null` if object has not been fetched via `ViewableArticleList` or if the active user is a guest
 */
class ViewableArticle extends DatabaseObjectDecorator {
	/**
	 * @inheritDoc
	 */
	protected static $baseClass = Article::class;
	
	/**
	 * user profile object
	 * @var	UserProfile
	 */
	protected $userProfile = null;
	
	/**
	 * effective visit time
	 * @var	integer
	 */
	protected $effectiveVisitTime;
	
	/**
	 * number of unread articles
	 * @var	integer
	 */
	protected static $unreadArticles;
	
	/**
	 * Returns a specific article decorated as viewable article or `null` if it does not exist.
	 *
	 * @param	integer		$articleID
	 * @param       boolean         $enableContentLoading   Enables/disables the loading of article content objects
	 * @return	ViewableArticle
	 */
	public static function getArticle($articleID, $enableContentLoading = true) {
		$list = new ViewableArticleList();
		$list->enableContentLoading($enableContentLoading);
		$list->setObjectIDs([$articleID]);
		$list->readObjects();
		$objects = $list->getObjects();
		if (isset($objects[$articleID])) return $objects[$articleID];
		return null;
	}
	
	/**
	 * Returns the user profile object.
	 *
	 * @return	UserProfile
	 */
	public function getUserProfile() {
		if ($this->userProfile === null) {
			if ($this->userID) {
				$this->userProfile = UserProfileRuntimeCache::getInstance()->getObject($this->userID);
			}
			else {
				$this->userProfile = new UserProfile(new User(null, [
					'username' => $this->username
				]));
			}
		}
		
		return $this->userProfile;
	}
	
	/**
	 * Sets the article's content.
	 *
	 * @param       ViewableArticleContent  $articleContent
	 */
	public function setArticleContent(ViewableArticleContent $articleContent) {
		if ($this->getDecoratedObject()->articleContents === null) {
			$this->getDecoratedObject()->articleContents = [];
		}
		
		$this->getDecoratedObject()->articleContents[$articleContent->languageID ?: 0] = $articleContent;
	}
	
	/**
	 * Returns the article's image.
	 * 
	 * @return	ViewableMedia|null
	 */
	public function getImage() {
		if ($this->getArticleContent() !== null) {
			return $this->getArticleContent()->getImage();
		}
		
		return null;
	}
	
	/**
	 * Returns the article's teaser image.
	 *
	 * @return	ViewableMedia|null
	 */
	public function getTeaserImage() {
		if ($this->getArticleContent() !== null) {
			return $this->getArticleContent()->getTeaserImage();
		}
		
		return null;
	}
	
	/**
	 * Returns the effective visit time.
	 *
	 * @return	integer
	 */
	public function getVisitTime() {
		if ($this->effectiveVisitTime === null) {
			if (WCF::getUser()->userID) {
				$this->effectiveVisitTime = max($this->visitTime, VisitTracker::getInstance()->getVisitTime('com.woltlab.wcf.article'));
			}
			else {
				$this->effectiveVisitTime = max(VisitTracker::getInstance()->getObjectVisitTime('com.woltlab.wcf.article', $this->articleID), VisitTracker::getInstance()->getVisitTime('com.woltlab.wcf.article'));
			}
			if ($this->effectiveVisitTime === null) {
				$this->effectiveVisitTime = 0;
			}
		}
		
		return $this->effectiveVisitTime;
	}
	
	/**
	 * Returns true if this article is new for the active user.
	 *
	 * @return	boolean
	 */
	public function isNew() {
		return $this->time > $this->getVisitTime();
	}
	
	/**
	 * Returns the number of unread articles.
	 *
	 * @return	integer
	 */
	public static function getUnreadArticles() {
		if (self::$unreadArticles === null) {
			self::$unreadArticles = 0;
			
			if (WCF::getUser()->userID) {
				$unreadArticles = UserStorageHandler::getInstance()->getField('unreadArticles');
				
				// cache does not exist or is outdated
				if ($unreadArticles === null) {
					$categoryIDs = ArticleCategory::getAccessibleCategoryIDs();
					if (!empty($categoryIDs)) {
						$conditionBuilder = new PreparedStatementConditionBuilder();
						$conditionBuilder->add('article.categoryID IN (?)', [$categoryIDs]);
						$conditionBuilder->add('article.time > ?', [VisitTracker::getInstance()->getVisitTime('com.woltlab.wcf.article')]);
						$conditionBuilder->add('article.isDeleted = ?', [0]);
						$conditionBuilder->add('article.publicationStatus = ?', [Article::PUBLISHED]);
						$conditionBuilder->add('(article.time > tracked_visit.visitTime OR tracked_visit.visitTime IS NULL)');
						
						$sql = "SELECT		COUNT(*)
							FROM		wcf".WCF_N."_article article
							LEFT JOIN	wcf".WCF_N."_tracked_visit tracked_visit
							ON		(tracked_visit.objectTypeID = ".VisitTracker::getInstance()->getObjectTypeID('com.woltlab.wcf.article')."
									AND tracked_visit.objectID = article.articleID
									AND tracked_visit.userID = ".WCF::getUser()->userID.")
							".$conditionBuilder;
						$statement = WCF::getDB()->prepareStatement($sql);
						$statement->execute($conditionBuilder->getParameters());
						self::$unreadArticles = $statement->fetchSingleColumn();
					}
					
					// update storage unreadEntries
					UserStorageHandler::getInstance()->update(WCF::getUser()->userID, 'unreadArticles', self::$unreadArticles);
				}
				else {
					self::$unreadArticles = $unreadArticles;
				}
			}
		}
		
		return self::$unreadArticles;
	}
}
