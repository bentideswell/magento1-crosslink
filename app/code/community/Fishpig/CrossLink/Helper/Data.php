<?php
/**
 * @category  Fishpig
 * @package  Fishpig_CrossLink
 * @license    http://fishpig.co.uk/license.txt
 * @author    Ben Tideswell <help@fishpig.co.uk>
 */

class Fishpig_CrossLink_Helper_Data extends Mage_Core_Helper_Abstract
{
	/**
	 * Determine whether CrossLinks is enabled or not
	 *
	 * @return bool
	 */
	public function isEnabled()
	{
		return Mage::getStoreConfigFlag('crosslink/general/enabled');
	}
}
