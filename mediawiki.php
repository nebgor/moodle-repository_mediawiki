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
 * wikimedia class
 * class for communication with Wikimedia Commons API
 *
 * @author Aparup Banerjee <aparup@moodle.com>, Dongsheng Cai <dongsheng@moodle.com>, Raul Kern <raunator@gmail.com>
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License
 */

define('MEDIAWIKI_THUMBS_PER_PAGE', 24);
define('MEDIAWIKI_FILE_NS', 6);
define('MEDIAWIKI_IMAGE_SIDE_LENGTH', 1024);
define('MEDIAWIKI_THUMB_SIZE', 120);

class mediawiki {
    private $_conn  = null;
    private $_param = array();

    public function __construct($url = '') {
        if (empty($url)) {
            $this->api = 'http://commons.wikimedia.org/w/api.php';
        } else {
            $this->api = $url;
        }
        $this->_param['format'] = 'php';
        $this->_param['redirects'] = true;
        $this->_conn = new curl(array('cache'=>true, 'debug'=>false));
    }
    public function login($user, $pass) {
        $this->_param['action']   = 'login';
        $this->_param['lgname']   = $user;
        $this->_param['lgpassword'] = $pass;
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['result']['sessionid'])) {
            $this->userid = $result['result']['lguserid'];
            $this->username = $result['result']['lgusername'];
            $this->token = $result['result']['lgtoken'];
            return true;
        } else {
            return false;
        }
    }
    public function logout() {
        $this->_param['action']   = 'logout';
        $content = $this->_conn->post($this->api, $this->_param);
        return;
    }
    public function get_image_url($titles) {
        $imageurls = array();
        $this->_param['action'] = 'query';
        if (is_array($titles)) {
            foreach ($titles as $title) {
                $this->_param['titles'] .= ('|'.urldecode($title));
            }
        } else {
            $this->_param['titles'] = urldecode($title);
        }
        $this->_param['prop']   = 'imageinfo';
        $this->_param['iiprop'] = 'url';
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        foreach ($result['query']['pages'] as $page) {
            if (!empty($page['imageinfo'][0]['url'])) {
                $imageurls[] = $page['imageinfo'][0]['url'];
            }
        }
        return $imageurls;
    }
    public function get_images_by_page($title) {
        $imageurls = array();
        $this->_param['action'] = 'query';
        $this->_param['generator'] = 'images';
        $this->_param['titles'] = urldecode($title);
        $this->_param['prop']   = 'images|info|imageinfo';
        $this->_param['iiprop'] = 'url';
        $content = $this->_conn->post($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['query']['pages'])) {
            foreach ($result['query']['pages'] as $page) {
                $imageurls[$page['title']] = $page['imageinfo'][0]['url'];
            }
        }
        return $imageurls;
    }
    /**
     * Generate thumbnail URL from image URL.
     *
     * @param string $commonsmaindir url to base file dir for images.
     * @param string $imageurl
     * @param int $origwidth
     * @param int $origheight
     * @param int $thumbwidth
     * @global object OUTPUT
     * @return string
     */
    public function get_thumb_url($commonsmaindir, $imageurl, $origwidth, $origheight, $thumbwidth=75) {
        global $OUTPUT;

        if ($origwidth <= $thumbwidth AND $origheight <= $thumbwidth) {
            return $imageurl;
        } else {
            $thumburl = '';
            if ($imageurl) {
                $shortpath = str_replace($commonsmaindir, '', $imageurl);
                $extension = strtolower(pathinfo($shortpath, PATHINFO_EXTENSION));
                if (strcmp($extension, 'gif') == 0) {  // no thumb for gifs
                    return $OUTPUT->pix_url(file_extension_icon('.gif', $thumbwidth))->out(false);
                }
                $dirparts = explode('/', $shortpath);
                $filename = end($dirparts);
                if ($origheight > $origwidth) {
                    $thumbwidth = round($thumbwidth * $origwidth/$origheight);
                }
                $commonsurlhost = parse_url($commonsmaindir, PHP_URL_HOST);
                if ($commonsurlhost == 'upload.wikimedia.org') { // specific location for wikimedia.org
                    $thumburl = $commonsmaindir. 'thumb/'
                            . implode('/', $dirparts) . '/'. $thumbwidth .'px-' . $filename;
                } else {
                    $firstdir = array_shift($dirparts);
                    $thumburl = $commonsmaindir. $firstdir. '/thumb/'
                            . implode('/', $dirparts). '/'. $thumbwidth .'px-' . $filename;
                }
                if (strcmp($extension, 'svg') == 0) {  // png thumb for svg-s
                    $thumburl .= '.png';
                }
            }
            return $thumburl;
        }
    }

    /**
     * Search for images and return photos array.
     *
     * @param string $keyword
     * @param int $page
     * @param array $params additional query params
     * @return array
     */
    public function search_images($commonsdir, $keyword, $page = 0, $params = array()) {
        global $OUTPUT;
        $filesarray = array();
        $this->_param['action'] = 'query';
        $this->_param['generator'] = 'search';
        $this->_param['gsrsearch'] = $keyword;
        $this->_param['gsrnamespace'] = MEDIAWIKI_FILE_NS;
        $this->_param['gsrlimit'] = MEDIAWIKI_THUMBS_PER_PAGE;
        $this->_param['gsroffset'] = $page * MEDIAWIKI_THUMBS_PER_PAGE;
        $this->_param['prop']   = 'imageinfo';
        $this->_param['iiprop'] = 'url|dimensions|mime|timestamp|size|user';
        $this->_param += $params;
        $this->_param += array('iiurlwidth' => MEDIAWIKI_IMAGE_SIDE_LENGTH,
            'iiurlheight' => MEDIAWIKI_IMAGE_SIDE_LENGTH);
        // didn't work with POST
        $content = $this->_conn->get($this->api, $this->_param);
        $result = unserialize($content);
        if (!empty($result['query']['pages'])) {
            foreach ($result['query']['pages'] as $page) {
                $title = $page['title'];
                $filetype = $page['imageinfo'][0]['mime'];
                $imagetypes = array('image/jpeg', 'image/png', 'image/gif', 'image/svg+xml');
                if (in_array($filetype, $imagetypes)) {  // is image
                    $extension = pathinfo($title, PATHINFO_EXTENSION);
                    if (strcmp($extension, 'svg') == 0) {               // upload png version of svg-s
                        $title .= '.png';
                    }
                    if ($page['imageinfo'][0]['thumbwidth'] < $page['imageinfo'][0]['width']) {
                        $attrs = array(
                            // upload scaled down image
                            'source' => $page['imageinfo'][0]['thumburl'],
                            'image_width' => $page['imageinfo'][0]['thumbwidth'],
                            'image_height' => $page['imageinfo'][0]['thumbheight']
                        );
                        if ($attrs['image_width'] <= MEDIAWIKI_THUMB_SIZE && $attrs['image_height'] <= MEDIAWIKI_THUMB_SIZE) {
                            $attrs['realthumbnail'] = $attrs['source'];
                        }
                        if ($attrs['image_width'] <= 24 && $attrs['image_height'] <= 24) {
                            $attrs['realicon'] = $attrs['source'];
                        }
                    } else {
                        $attrs = array(
                            // upload full size image
                            'source' => $page['imageinfo'][0]['url'],
                            'image_width' => $page['imageinfo'][0]['width'],
                            'image_height' => $page['imageinfo'][0]['height'],
                            'size' => $page['imageinfo'][0]['size']
                        );
                    }
                    $attrs += array(
                        'realthumbnail' => $this->get_thumb_url($commonsdir, $page['imageinfo'][0]['url'],
                                $page['imageinfo'][0]['width'], $page['imageinfo'][0]['height'], MEDIAWIKI_THUMB_SIZE),
                        'realicon' => $this->get_thumb_url($commonsdir, $page['imageinfo'][0]['url'],
                                $page['imageinfo'][0]['width'], $page['imageinfo'][0]['height'], 24),
                        'author' => $page['imageinfo'][0]['user'],
                        'datemodified' => strtotime($page['imageinfo'][0]['timestamp']),
                        );
                } else {  // other file types
                    $attrs = array('source' => $page['imageinfo'][0]['url']);
                }
                $filesarray[] = array(
                    'title'=>substr($title, 5),         // chop off 'File:'
                    'thumbnail' => $OUTPUT->pix_url(file_extension_icon(substr($title, 5), MEDIAWIKI_THUMB_SIZE))->out(false),
                    'thumbnail_width' => MEDIAWIKI_THUMB_SIZE,
                    'thumbnail_height' => MEDIAWIKI_THUMB_SIZE,
                    'license' => 'cc-sa',
                    // the accessible url of the file
                    'url'=>$page['imageinfo'][0]['descriptionurl']
                ) + $attrs;
            }
        }
        return $filesarray;
    }

}
