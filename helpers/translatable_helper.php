<?php
/**
 * FUEL CMS
 * http://www.getfuelcms.com
 *
 * An open source Content Management System based on the
 * Codeigniter framework (http://codeigniter.com)
 *
 * @package		FUEL CMS
 * @author		David McReynolds @ Daylight Studio
 * @copyright	Copyright (c) 2017, Daylight Studio LLC.
 * @license		http://docs.getfuelcms.com/general/license
 * @link		http://www.getfuelcms.com
 */

// ------------------------------------------------------------------------

/**
 * Translatable Helper
 *
 * Contains functions for the Translatable module
 *
 * @package		User Guide
 * @subpackage	Helpers
 * @category	Helpers
 */

// --------------------------------------------------------------------

/**
 * @param $language_key string a two-letter country code
 * @return string|null image of the flag if found, else null
 */
function get_flag_for_language($language_key) {
    $filename = "$language_key.png";
    if (asset_exists("flag_icons/$filename", 'images', TRANSLATABLE_FOLDER)) {
        $path = img_path("flag_icons/$filename", TRANSLATABLE_FOLDER, TRUE);
        return img($path);
    } else {
        return null;
    }
}
