<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin is used to access mediawiki files
 *
 * @since 2.0
 * @package    repository_mediawiki
 * @copyright  2013 Aparup Banerjee aparup@moodle.com
 * @copyright  2010 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
require_once($CFG->dirroot . '/repository/lib.php');
require_once(dirname(__FILE__) . '/mediawiki.php');

/**
 * repository_mediawiki class
 * This is a class used to browse images from mediawiki
 *
 * @since 2.0
 * @package    repository_mediawiki
 * @copyright  2009 Dongsheng Cai {@link http://dongsheng.org}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class repository_mediawiki extends repository {
    public function __construct($repositoryid, $context = SYSCONTEXTID, $options = array()) {
        global $SESSION;
        parent::__construct($repositoryid, $context, $options);
        $this->keyword = optional_param('mediawiki_keyword', '', PARAM_RAW);
        if (empty($this->keyword)) {
            $this->keyword = optional_param('s', '', PARAM_RAW);
        }
        $sesskeyword = 'mediawiki_'. $this->id. '_keyword';
        if (empty($this->keyword) && optional_param('page', '', PARAM_RAW)) {
            // This is the request of another page for the last search, retrieve the cached keyword
            if (isset($SESSION->{$sesskeyword})) {
                $this->keyword = $SESSION->{$sesskeyword};
            }
        } else if (!empty($this->keyword)) {
            // save the search keyword in the session so we can retrieve it later
            $SESSION->{$sesskeyword} = $this->keyword;
        }
    }

    /**
     * Returns maximum width for images
     *
     * Takes the maximum width for images eithre from search form or from
     * user preferences, updates user preferences if needed
     *
     * @return int
     */
    public function get_maxwidth() {
        $param = optional_param('mediawiki_maxwidth', 0, PARAM_INT);
        $pref = get_user_preferences('repository_mediawiki_maxwidth', MEDIAWIKI_IMAGE_SIDE_LENGTH);
        if ($param > 0 && $param != $pref) {
            $pref = $param;
            set_user_preference('repository_mediawiki_maxwidth', $pref);
        }
        return $pref;
    }

    /**
     * Returns maximum height for images
     *
     * Takes the maximum height for images eithre from search form or from
     * user preferences, updates user preferences if needed
     *
     * @return int
     */
    public function get_maxheight() {
        $param = optional_param('mediawiki_maxheight', 0, PARAM_INT);
        $pref = get_user_preferences('repository_mediawiki_maxheight', MEDIAWIKI_IMAGE_SIDE_LENGTH);
        if ($param > 0 && $param != $pref) {
            $pref = $param;
            set_user_preference('repository_mediawiki_maxheight', $pref);
        }
        return $pref;
    }

    public function get_url() {
        if (substr($this->options['mediawiki_url'], -1, 1) != '/') {
            $this->options['mediawiki_url'] .= '/';
        }
        return $this->options['mediawiki_url'];
    }
    public function get_api_endpoint() {
        $url = $this->get_url();
        // add mediawiki endpoint to a mediawiki url.
        if (substr($url, -7, 7) != 'api.php') {
            $url .= 'api.php';
        }
        return $url;
    }
    public function get_common_dir() {
        if (empty($this->options['mediawiki_commonsurl'])) {
            $this->options['mediawiki_commonsurl'] = $this->get_url();
        }
        return$this->options['mediawiki_commonsurl'];
    }

    public function get_listing($path = '', $page = '') {
        $client = new mediawiki($this->get_api_endpoint());
        $list = array();
        $list['page'] = (int)$page;
        if ($list['page'] < 1) {
            $list['page'] = 1;
        }

        $list['list'] = $client->search_images($this->get_common_dir(), $this->keyword, $list['page'] - 1,
                array('iiurlwidth' => $this->get_maxwidth(),
                    'iiurlheight' => $this->get_maxheight()));
        $list['nologin'] = true;
        $list['norefresh'] = true;
        $list['nosearch'] = true;
        if (!empty($list['list'])) {
            $list['pages'] = -1; // means we don't know exactly how many pages there are but we can always jump to the next page
        } else if ($list['page'] > 1) {
            $list['pages'] = $list['page']; // no images available on this page, this is the last page
        } else {
            $list['pages'] = 0; // no paging
        }
        return $list;
    }
    // login
    public function check_login() {
        return !empty($this->keyword);
    }
    // if check_login returns false,
    // this function will be called to print a login form.
    public function print_login() {
        $keyword = new stdClass();
        $keyword->label = get_string('keyword', 'repository_mediawiki').': ';
        $keyword->id    = 'input_text_keyword';
        $keyword->type  = 'text';
        $keyword->name  = 'mediawiki_keyword';
        $keyword->value = '';
        $maxwidth = array(
            'label' => get_string('maxwidth', 'repository_mediawiki').': ',
            'type' => 'text',
            'name' => 'mediawiki_maxwidth',
            'value' => get_user_preferences('repository_mediawiki_maxwidth', MEDIAWIKI_IMAGE_SIDE_LENGTH),
        );
        $maxheight = array(
            'label' => get_string('maxheight', 'repository_mediawiki').': ',
            'type' => 'text',
            'name' => 'mediawiki_maxheight',
            'value' => get_user_preferences('repository_mediawiki_maxheight', MEDIAWIKI_IMAGE_SIDE_LENGTH),
        );
        if ($this->options['ajax']) {
            $form = array();
            $form['login'] = array($keyword, (object)$maxwidth, (object)$maxheight);
            $form['nologin'] = true;
            $form['norefresh'] = true;
            $form['nosearch'] = true;
            $form['allowcaching'] = false; // indicates that login form can NOT
            // be cached in filepicker.js (maxwidth and maxheight are dynamic)
            return $form;
        } else {
            echo <<<EOD
<table>
<tr>
<td>{$keyword->label}</td><td><input name="{$keyword->name}" type="text" /></td>
</tr>
</table>
<input type="submit" />
EOD;
        }
    }
    // search
    // if this plugin support global search, if this function return
    // true, search function will be called when global searching working
    public function global_search() {
        return false;
    }
    public function search($searchtext, $page = 0) {
        $client = new mediawiki($this->get_api_endpoint());
        $searchresult = array();
        $searchresult['list'] = $client->search_images($this->get_common_dir(), $searchtext);
        return $searchresult;
    }
    // when logout button on file picker is clicked, this function will be
    // called.
    public function logout() {
        return $this->print_login();
    }
    public function supported_returntypes() {
        return (FILE_INTERNAL | FILE_EXTERNAL);
    }

    /**
     * Return the source information
     *
     * @param stdClass $url
     * @return string|null
     */
    public function get_file_source_info($url) {
        return $url;
    }

    /**
     * Is this repository accessing private data?
     *
     * @return bool
     */
    public function contains_private_data() {
        return false;
    }

    /**
     * Return names of the options to display in the repository instance form
     *
     * @return array of option names
     */
    public static function get_instance_option_names() {
        return array('mediawiki_url', 'mediawiki_commonsurl');
    }
    public static function instance_config_form($mform) {
        $strrequired = get_string('required');
        $mform->addElement('text', 'mediawiki_url', get_string('mediawiki_url', 'repository_mediawiki'));
        $mform->addRule('mediawiki_url', $strrequired, 'required', null, 'client');
        $mform->setType('mediawiki_url', PARAM_URL);
        $mform->setDefault('mediawiki_url', 'http://commons.wikimedia.org/w/');
        $mform->addElement('text', 'mediawiki_commonsurl', get_string('mediawiki_commonsurl', 'repository_mediawiki'));
        $mform->setType('mediawiki_commonsurl', PARAM_URL);
        $mform->setDefault('mediawiki_commonsurl', 'http://upload.wikimedia.org/wikipedia/commons/');
    }
}
