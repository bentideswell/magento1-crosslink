<?php
/*
 * @category  Fishpig
 * @package   Fishpig_CrossLink
 * @license   http://fishpig.co.uk/license.txt
 * @author    Ben Tideswell <help@fishpig.co.uk>
 * @SkipObfuscation
 */
class Fishpig_CrossLink_Model_Observer extends Varien_Object
{
	/**
	 * Tags used to skip blocks
	 *
	 * @const string
	 */
	const SKIP_TAG_START = '<!--CL-Skip-->';
	const SKIP_TAG_END = '<!--/CL-Skip-->';
	
	/**
	 * parameter option constants
	 *
	 * @const int
	 */
	const SIBLING_CHAR_AFTER = 1;
	const SIBLING_CHAR_BEFORE = 2;

	/**
	 * Multi byte encoding
	 *
	 * @const string
	 */
	const MB_ENCODING = 'UTF-8';
	 
	/**
	 * Blocks to be skipped by CrossLinks
	 *
	 * @var array
	 */
	protected $_skipBlocks = array(
		'header',
		'footer',
		'left',
		'right',
		'sidebar',
		'breadcrumbs',
		'product.info.media',
	);

	/**
	 * Allows storage of HTML in exchange for a key
	 * Key's can be swapped from HTML to reverse the process
	 *
	 * @var array
	 */
	protected $_safe = array();
	
	/**
	 * Initialise self::$_skipBlocks
	 *
	 * @return void
	 */
	protected function _construct()
	{
		if (($blocks = trim(Mage::getStoreConfig('crosslink/target/ignore_blocks'), ", \n\r")) !== '') {
			$blocks = explode(',', $blocks);
			
			foreach($blocks as $block) {
				if ($block && !in_array($block, $this->_skipBlocks)) {
					$this->_skipBlocks[] = $block;
				}
			}
		}
		
		return parent::_construct();
	}
	
	/**
	 * Add comments to blocks that should be skipped
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function addBlockCommentsObserver(Varien_Event_Observer $observer)
	{
		if (!Mage::helper('crosslink')->isEnabled() || $this->isAjaxRequest() || !$this->isValidRoute()) {
			return $this;
		}
		
		if (in_array($observer->getEvent()->getBlock()->getNameInLayout(), $this->_skipBlocks)) {
			$transport = $observer->getEvent()->getTransport();
			
			$transport->setHtml(
				self::SKIP_TAG_START . $transport->getHtml() . self::SKIP_TAG_END
			);
		}
		
		return $this;
	}
	
	/**
	 * Inject links to the HTML response
	 *
	 * @param Varien_Event_Observer $observer
	 * @return $this
	 */
	public function injectLinksObserver(Varien_Event_Observer $observer)
	{
		if (!Mage::helper('crosslink')->isEnabled() || $this->isAjaxRequest() || !$this->isValidRoute()) {
			return $this;
		}

		$front = $observer->getEvent()->getFront();
		$html = $front->getResponse()->getBody();
		$links = $this->getLinkData();
		
		if (!$links) {
			return $this;
		}
		
		try {
			$originalEncoding = mb_internal_encoding();
			@mb_internal_encoding(self::MB_ENCODING);

			if (($html = $this->injectLinks($html, $links)) !== '') {
				$front->getResponse()->setBody($html);
			}
			
			@mb_internal_encoding($originalEncoding);
		}
		catch (Exception $e) {
			Mage::logException($e);
		}

		return $this;
	}
	
	/**
	 * Inject the $links into $html
	 *
	 * @param string $html
	 * @param array $links = array
	 * @return string
	 */
	public function injectLinks($html, array $links = array())
	{
		$html = $this->_processRawHtml($html);
		$maxGlobalLinks = $this->getLinkLimit('request');

		foreach($links as $link => $keywords) {
			if ($maxGlobalLinks < 1) {
				break;
			}

			$maxUrlLinks = $this->getLinkLimit('url');

			foreach($keywords as $keyword) {
				if ($maxUrlLinks < 1 || $maxGlobalLinks < 1) {
					break;
				}
				
				$maxKeywordLinks = $this->getLinkLimit('keyword');

				while($maxKeywordLinks > 0) {
					$result = $this->_injectLink($html, $link, $keyword);
					
					if ($result === true) {
						--$maxKeywordLinks;
						--$maxUrlLinks;
						--$maxGlobalLinks;					

						if ($maxUrlLinks < 1 || $maxGlobalLinks < 1) {
							break;
						}
					}
					else if ($result === false) {
						break;
					}
				}
			}
		}

		$this->emptySafe($html);
		
		return trim($html);
	}

	/**
	 * Wrapper for preg_replace_callback
	 *
	 * @param string $pattern
	 * @param string $callback
	 * @param string $source
	 * @return string
	 */
	protected function _pregReplaceCallback($pattern, $callback, $source)
	{
		if (preg_match_all($pattern, $source, $result)) {
			foreach($result[1] as $match) {
				$source = str_replace($match, $this->addToSafe($match), $source);
			}
		}
		
		return $source;
	}
	
	/**
	 * Clean the HTML and removed all content that is not linkable
	 *
	 * @param string $html
	 * @return string
	 */
	protected function _processRawHtml($html)
	{
		// Strip HTML Head
		$html = $this->_pregReplaceCallback('/^(.*<body.*>)/iUs', array($this, 'addToSafe'), trim($html));

		// Strip skip blocks
		$html = $this->_pregReplaceCallback(sprintf("/(%s.*%s)/Us", preg_quote(self::SKIP_TAG_START, '/'), preg_quote(self::SKIP_TAG_END, '/')), array($this, 'addToSafe'), trim($html));

		// Save some tags
		$tags = array('script', 'noscript', 'h[1-8]{1}', 'iframe', 'textarea', 'address', 'fb:comments', 'pre', 'button', 'select', 'a');
		
		$html = $this->_pregReplaceCallback('/(<(' . implode('|', $tags) . ')[^>]{0,}>.*<\/\\2>)/iUs', array($this, 'addToSafe'), $html);

		// Clean the HTML
//		$html = preg_replace("/\s+/", ' ', $html);

		// Add tags with parameters to the safe
		$html = $this->_pregReplaceCallback('/(<[a-z]{1,} .*>)/iU', array($this, 'addToSafe'), $html);

		// Pad the HTML to make things a little easier
		return ' ' . $html . ' ';
	}
	
	/**
	 * Retrieve the link data
	 *
	 * @return array
	 */
	public function getLinkData()
	{
		$links = array();
		
		if ($keywords = Mage::getStoreConfig('crosslink/custom/keywords')) {
			$keywords = unserialize($keywords);

			foreach($keywords as $keyword) {
				if (!isset($links[$keyword['url']])) {
					$links[$keyword['url']] = array();
				}
				
				foreach(explode(',', trim($keyword['keywords'], ', ')) as $key) {
					if (($key = trim($key)) !== '') {
						$links[$keyword['url']][] = trim($key);
					}
				}
			}
		}
		
		if (count($links) === 0) {
			return false;
		}

		$urls = array(
			Mage::Helper('core/url')->getCurrentUrl(),
			parse_url(Mage::Helper('core/url')->getCurrentUrl(), PHP_URL_PATH),
		);

		
		if (strpos($urls[0], '?') !== false) {
			$urls[] = substr($urls[0], 0, strpos($urls[0], '?'));	
		}
		
		foreach($urls as $url) {
			if (isset($links[$url])) {
				unset($links[$url]);
			}
		}
		
		return $links;
	}
	
	/**
	 * Add some content to the safe
	 *
	 * @param string $html
	 * @return string 
	 */
	public function addToSafe($html)
	{
		$key = count($this->_safe);

		$html = is_array($html) ? $html[1] : $html;

		$this->_safe[$key] = $html;	
		
		return $this->generateSafeKey($key);
	}
	
	/**
	 * Swap keys for content in the safe
	 *
	 * @param string $content
	 * @return $this
	 */
	public function emptySafe(&$content)
	{
		$values = array_reverse($this->_safe, true);

		foreach($values as $key => $value) {
			$content = str_replace($this->generateSafeKey($key), $value, $content);
		}
		
		return $this;
	}
	
	/**
	 * Generate a key for the safe
	 *
	 * @param int $i
	 * @param int $door = 0
	 * @return string
	 */
	public function generateSafeKey($i)
	{
		return sprintf('<!--SF-%d-->', $i);
	}
	
	/**
	 * Inject a link into $html using $link and $keyword
	 *
	 * @param string &$html
	 * @param string $link
	 * @param string $keyword
	 * @param int $door = 0
	 * @return bool|null
	 */
	protected function _injectLink(&$html, $link, $keyword)
	{
		$offset = 0;
		$strpos = $this->isCaseSensitive() ? 'mb_strpos' : 'mb_stripos';

		while (($position = $strpos($html, $keyword, $offset)) !== false) {
			$offset = $position + mb_strlen($keyword);

			$charAfter = $this->_getSiblingChar($html, $position, $keyword, self::SIBLING_CHAR_AFTER);
			$charBefore = $this->_getSiblingChar($html, $position, $keyword, self::SIBLING_CHAR_BEFORE);			

			$origKeyword = mb_substr($html, $position, mb_strlen($keyword));
			$realKeyword = $origKeyword;
			$perfectMatch = true;

			if ($this->isAlphaNumericChar($charBefore)) {
				$perfectMatch = false;
				$beforeRealKeyword = mb_strpos($html, 0, $position);

				if (preg_match('/([a-zA-Z0-9]{1,})$/x', $beforeRealKeyword, $matches)) {
					$realKeyword = $matches[1] . $origKeyword;
				}
			}
			
			if ($this->isAlphaNumericChar($charAfter)) {
				$perfectMatch = false;
				$afterRealKeyword = mb_strpos($html, $position+mb_strlen($origKeyword));

				if (preg_match('/^(.*)[^a-zA-Z0-9]{1}/U', $afterRealKeyword, $matches)) {
					$realKeyword .= $matches[1];
				}
				
				$safeKey = $this->addToSafe($realKeyword);
			}

			if ($perfectMatch !== false) {
				$safeKey = $this->addToSafe($this->_generateLinkTag($link, $realKeyword));
				$head = mb_substr($html, 0, $position);
				$html = $head . $safeKey . mb_substr($html, mb_strlen($head) + mb_strlen($realKeyword));
			
				return true;
			}
		}

		return false;
	}
	
	/**
	 * Get a sibling character (previous or after)
	 *
	 * @param string $html
	 * @param int $position
	 * @param string $keyword
	 * @param int $siblingType = 2
	 * @return string
	 */
	protected function _getSiblingChar(&$html, $position, $keyword, $siblingType = 2)
	{
		$charsets = array(
			1 => 'ASCII',
			2 => 'UTF-8',
		);

		foreach($charsets as $offset => $charset) {
			if ($siblingType === self::SIBLING_CHAR_AFTER) {
				$char = mb_substr($html, $position + mb_strlen($keyword), $offset);
			}
			else {
				$char = mb_substr($html, $position-$offset, $offset);
			}
			
			if (mb_check_encoding($char, $charset)) {
				return $char;
			}
		}
		
		return '';
	}

	/**
	 * Generate a HTML link tag
	 *
	 * @param string $href
	 * @param string $anchor
	 * @return string
	 */
	protected function _generateLinkTag($href, $anchor)
	{
		return sprintf('<a href="%s" class="cl">%s</a>', $href, $anchor);
	}

	/**
	 * Determine whether $char is a letter or a number
	 *
	 * @param string $char
	 * @return bool
	 */
	public function isAlphaNumericChar($char)
	{
		if (mb_strlen($char) === 2) {
			return preg_match('/[\w\p{L}\p{N}\p{Pd}]+/u', $char);
		}

		$char = ord(strtolower($char));

		return ($char >= ord('a') && $char <= ord('z')) || ($char >= ord('0') && $char <= ord('9'));;		
	}
	
	/**
	 * Determine whether matching is case sensitive
	 *
	 * @return bool
	 */
	public function isCaseSensitive()
	{
		return Mage::getStoreConfig('crosslink/general/case_sensitive');
	}
	
	/**
	 * Retrieve the total number of links allowed
	 *
	 * @return int
	 */
	public function getLinkLimit($key)
	{
		return ($max = (int)Mage::getStoreConfig('crosslink/limit/' . $key)) > 0 ? $max : 9999;
	}	
	
	/**
	 * Determine whether the current route is a valid route
	 *
	 * @return bool
	 */
	public function isValidRoute()
	{
		if (($handleString = trim(Mage::getStoreConfig('crosslink/target/layout_handles'))) === '') {
			return false;
		}

		$vhandles = explode("\n", preg_replace('/[^a-z0-9_\n]{1,}/Ui', '', $handleString));
		$chandles = Mage::getSingleton('core/layout')->getUpdate()->getHandles();

		return count(array_intersect($vhandles, $chandles)) > 0;
	}

	/**
	 * Determine whther the current request is an Ajax request
	 *
	 * @return bool
	**/	
	public function isAjaxRequest()
	{
		return isset($_GET['isAjax']) || isset($_GET['isLayerAjax']);
	}
}
