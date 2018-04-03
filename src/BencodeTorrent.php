<?php

namespace ApolloRip\BencodeTorrent;

/**
 * BEncode service that allows us to encode PHP objects into BEncode and decode
 * BEncode into PHP objects for torrents. BEncode supports the following PHP objects:
 *      - Associated Array
 *      - Lists
 *      - Strings
 *      - Integers
 * with any other type throwing an exception. A list is defined for our purposes
 * as an array with only numeric keys in perfect order, otherwise we assume it's
 * an associated array and will encode as a dictionary.
 *
 * Additionally, as this is for torrent files, we can make the following assumptions
 * and requirements:
 *  1. Top level data structure must be a dictionary
 *  2. Dictionary must contain an info key
 * If any of these are violated, then we raise an exception for this particular file.
 *
 * @see https://wiki.theory.org/index.php/BitTorrentSpecification
 *
 * For Gazelle, this also acts as a unification of the two original BEncode implementations
 * which were both used in separate areas of the codebase.
 */
class BencodeTorrent {
    const FILELIST_DELIM = 0xF7;
    private static $utf8_filelist_delim = null;

    private $data;

    public function __construct() {
        $this->setDelim();
    }

    /**
     * Internal function that sets up the filelist_delim character for use. We cannot use encode
     * and char to set a class constant or variable, so we wait till the class is initialized
     * for the first time to set it.
     */
    private function setDelim() {
        if (self::$utf8_filelist_delim === null) {
            self::$utf8_filelist_delim = utf8_encode(chr(self::FILELIST_DELIM));
        }
    }

    /**
     * @param array $data
     * @throws \Exception
     */
    public function setData($data) {
        $this->data = $data;
        $this->validate();
    }

    /**
     * @param string $data
     * @throws \Exception
     */
    public function decodeData(string $data) {
        $this->data = $this->decode($data);
        $this->validate();
    }

    /**
     * @param string $path
     * @throws \Exception
     */
    public function decodeFile(string $path) {
        $this->data = $this->decode(file_get_contents($path, FILE_BINARY));
        $this->validate();
    }

    /**
     * @param string $data
     * @param int    $pos
     * @return array|bool|float|string
     */
    private function decode(string $data, int &$pos = 0) {
        if ($data[$pos] === 'd') {
            $pos++;
            $return = [];
            while ($data[$pos] !== 'e') {
                $key = $this->decode($data, $pos);
                $value = $this->decode($data, $pos);
                if (empty($key) || empty($value)) {
                    break;
                }
                $return[$key] = $value;
            }
            $pos++;
        }
        elseif ($data[$pos] === 'l') {
            $pos++;
            $return = [];
            while ($data[$pos] !== 'e') {
                $value = $this->decode($data, $pos);
                $return[] = $value;
            }
            $pos++;
        }
        elseif ($data[$pos] === 'i') {
            $pos++;
            $digits = strpos($data, 'e', $pos) - $pos;
            $return = (int) substr($data, $pos, $digits);
            $pos += $digits + 1;
        }
        else {
            $digits = strpos($data, ':', $pos) - $pos;
            $len = (int) substr($data, $pos, $digits);
            $pos += ($digits + 1);
            $return = substr($data, $pos, $len);
            $pos += $len;
        }
        return $return;
    }

    public function getData() {
        return $this->data;
    }

    /**
     * @throws \Exception
     */
    public function validate() {
        if (empty($this->data['info'])) {
            throw new \Exception("Torrent dictionary doesn't have info key");
        }
    }

    /**
     * @throws \RuntimeException
     */
    private function hasData() {
        if (empty($this->data) || !is_array($this->data)) {
            throw new \RuntimeException('Must decode proper bencode string first');
        }
    }

    /**
     * @return string
     */
    public function getEncode() {
        $this->hasData();
        return $this->encodeVal($this->data);
    }

    /**
     * @param mixed $data
     * @return string
     */
    private function encodeVal($data) {
        if (is_array($data)) {
            $return = '';
            $check = -1;
            $list = true;
            foreach ($data as $key => $value) {
                if ($key !== ++$check) {
                    $list = false;
                    break;
                }
            }

            if ($list) {
                $return .= 'l';
                foreach ($data as $value) {
                    $return .= $this->encodeVal($value);
                }
            }
            else {
                $return .= 'd';
                foreach ($data as $key => $value) {
                    $return .= $this->encodeVal(strval($key));
                    $return .= $this->encodeVal($value);
                }
            }
            $return .= 'e';
        }
        elseif (is_integer($data)) {
            $return = 'i'.$data.'e';
        }
        else {
            $return = strlen($data) . ':' . $data;
        }
        return $return;
    }

    /**
     * Utility function to clean out keys in the data and info dictionaries that we don't need in
     * our torrent file when we go to store it in the DB or serve it up to the user (with the
     * expectation that we'll be calling at least setAnnounceUrl(...) when a user asks for a valid
     * torrent file).
     *
     * @return bool flag to indicate if we altered the info dictionary
     */
    public function clean() {
        $this->cleanDataDictionary();
        return $this->cleanInfoDictionary();
    }

    /**
     * Clean out keys within the data dictionary that are not strictly necessary or will be
     * overwritten dynamically on any downloaded torrent (like announce or comment), so that we
     * store the smallest encoded string within the database and cuts down on potential waste.
     */
    public function cleanDataDictionary() {
        $allowed_keys = array('encoding', 'info');
        foreach ($this->data['info'] as $key => $value) {
            if (!in_array($key, $allowed_keys)) {
                unset($this->data['info'][$key]);
            }
        }
    }

    /**
     * Cleans out keys within the info dictionary (and would affect the generated info_hash)
     * that are not standard or expected. We do allow some keys that are not strictly necessary
     * (primarily the two below), but that's because it's better to just have the extra bits in
     * the dictionary than having to force a user to re-download the torrent file for something
     * that they might have no idea their client is doing nor how to stop it. Returns TRUE if
     * we had to change something in the info dictionary that would affect the info_hash (thus
     * requiring a re-download), else return FALSE.
     *
     * x_cross_seed is added by PyroCor (@see https://github.com/pyroscope/pyrocore)
     * unique is added by xseed (@see https://whatbox.ca/wiki/xseed)
     *
     * @return bool
     */
    public function cleanInfoDictionary() {
        $cleaned = false;
        $allowed_keys = array('files', 'name', 'piece length', 'pieces', 'private', 'length',
                              'name.utf8', 'name.utf-8', 'md5sum', 'sha1', 'source',
                              'file-duration', 'file-media', 'profiles', 'x_cross_seed', 'unique');
        foreach ($this->data['info'] as $key => $value) {
            if (!in_array($key, $allowed_keys)) {
                unset($this->data['info'][$key]);
                $cleaned = true;
            }
        }

        return $cleaned;
    }

    /**
     * Returns a bool on whether the private flag set to 1 within the info dictionary.
     *
     * @return bool
     */
    public function isPrivate() {
        $this->hasData();
        return isset($this->data['info']['private']) && $this->data['info']['private'] === 1;
    }

    /**
     * Sets the private flag (if not already set) in the info dictionary. Setting this to 1 makes
     * it so a client will only publish its presence in the swarm via the tracker in the announce
     * URL, else it'll be discoverable via other means such as PEX peer exchange or dht, which is
     * a negative for security and privacy of a private swarm. Returns a bool on whether or not
     * the flag was changed so that an appropriate screen can be shown to the user.
     *
     * @return bool
     */
    public function makePrivate() {
        $this->hasData();
        if ($this->isPrivate()) {
            return false;
        }
        $this->data['info']['private'] = 1;
        ksort($this->data['info']);
        return true;
    }

    /**
     * Set the source flag in the info dictionary equal to $source. This can be used to ensure a
     * unique info hash across sites so long as all sites use the source flag. This isn't an
     * 'official' flag (no accepted BEP on it), but it has become the defacto standard with more
     * clients supporting it natively. Returns a boolean on whether or not the source was changed
     * so that an appropriate screen can be shown to the user.
     *
     * @param string $source
     *
     * @return bool true if the source was set/changed, false if no change
     */
    public function setSource(string $source) {
        $this->hasData();
        if (isset($this->data['info']['source']) && $this->data['info']['source'] === $source) {
            return false;
        }
        // Set we've set the source and will require a download, we might as well clean
        // these out as well
        unset($this->data['info']['x_cross_seed']);
        unset($this->data['info']['unique']);
        $this->data['info']['source'] = $source;
        ksort($this->data['info']);
        return true;
    }

    /**
     * Function to allow you set any number of keys and values in the data dictionary.
     * @param array $array
     */
    public function set(array $array) {
        foreach ($array as $key => $value) {
            $this->data[$key] = $value;
            if (is_array($value)) {
                ksort($this->data[$key]);
            }
        }
        ksort($this->data);
    }

    /**
     * Sets the announce URL for current data. This URL forms the base of the GET request that
     * the torrent client will send to a tracker so that a client can get peer lists as well as
     * tell the tracker the stats on the download. The announce URL should probably also end with
     * /announce which allows for the more efficient scrape to happen on an initial handshake by
     * the client and when getting just the peer list.
     *
     * @see https://wiki.theory.org/index.php/BitTorrentSpecification#Tracker_HTTP.2FHTTPS_Protocol
     * @see https://wiki.theory.org/index.php/BitTorrentSpecification#Tracker_.27scrape.27_Convention
     *
     * @param string $announce_url
     */
    public function setAnnounceUrl(string $announce_url) {
        $this->hasData();
        $this->set(['announce' => $announce_url]);
    }

    /**
     * Sets the comment string for the current data. This does not affect the info_hash.
     * @param string $comment
     */
    public function setComment(string $comment) {
        $this->hasData();
        $this->set(['comment' => $comment]);
    }

    /**
     * Get a sha1 encoding of the BEncoded info dictionary. The SHA1 encoding allows us to transmit
     * the info dictionary over the wire (such as within URLs or in submitted forms). Gazelle
     * primarily relies on this so that it can ensure that all torrents uploaded have unique
     * info hashes and so a user could search for a torrent based on its info hash. The
     * BitTorrent protocol uses this when announcing/scraping a torrent so that the tracker can
     * identify the torrent the client is talking about.
     *
     * @return string
     */
    public function getInfoHash() {
        $this->hasData();
        return sha1($this->encodeVal($this->data['info']));
    }

    /**
     * @return string
     */
    public function getName() {
        if (isset($this->data['info']['name.utf-8'])) {
            return $this->data['info']['name.utf-8'];
        }
        return $this->data['info']['name'];
    }

    /**
     * Get the total size in bytes of the files in the torrent. For a single file torrent, it'll
     * just be the 'length' key in the 'info' dictionary, else we iterate through the 'files' list
     * and add up the 'length' of each element.
     *
     * @return int
     */
    public function getSize() {
        $cur_size = 0;
        if (!isset($this->data['info']['files'])) {
            $cur_size = $this->data['info']['length'];
        }
        else {
            foreach ($this->data['info']['files'] as $file) {
                $cur_size += $file['length'];
            }
        }
        return $cur_size;
    }

    /**
     * Get an array of files that are in the torrent, where each element is a array that contains
     * the keys 'name' and 'size'. For single torrent files, then we just take the name and length
     * keys from the info dictionary. For multiple file torrents, we then iterate through the
     * 'files' list where each element has 'length' and 'path' (which is a list of all components
     * of the path, which we can join together with '/').
     *
     * @return array
     */
    public function getFileList() {
        $files = [];
        if (!isset($this->data['info']['files'])) {
            // Single-file torrent
            $name = (isset($this->data['info']['name.utf-8']) ?
                $this->data['info']['name.utf-8'] :
                $this->data['info']['name']);
            $size = $this->data['info']['length'];
            $files[] = array('name' => $name, 'size' => $size);
        }
        else {
            $path_key = isset($this->data['info']['files'][0]['path.utf-8']) ?
                'path.utf-8' :
                'path';
            foreach ($this->data['info']['files'] as $file) {
                $tmp_path = array();
                foreach ($file[$path_key] as $sub_path) {
                    $tmp_path[] = $sub_path;
                }
                $files[] = array('name' => implode('/', $tmp_path), 'size' => $file['length']);
            }
            uasort(
                $files,
                function ($a, $b) {
                    return strnatcasecmp($a['name'], $b['name']);
                }
            );
        }
        return $files;
    }

    /**
     * Returns an array of strings formatted to be inserted into a Gazelle database into the table
     * torrents.FileList which is then used for displaying the table of files to the user when
     * viewing the group. Format of each string is:
     * {extension} s{size} {name} {delimiter}
     * We use the delimiter so that we can split the first three apart via ' ' and that then we
     * use the delimiter to find where the name ends.
     *
     * @return array
     */
    public function getGazelleFileList() {
        $files = [];
        foreach ($this->getFileList() as $file) {
            $name = $file['name'];
            $size = $file['length'];
            $name = self::makeUTF8(strtr($name, "\n\r\t", '   '));
            $ext_pos = strrpos($name, '.');
            // Should not be $ExtPos !== false. Extension-less files that start with a .
            // should not get extensions
            $ext = ($ext_pos ? trim(substr($name, $ext_pos + 1)) : '');
            $files[] =  sprintf("%s s%ds %s %s", ".$ext", $size, $name, self::$utf8_filelist_delim);
        }
        return $files;
    }

    /**
     * Given a string, convert it to UTF-8 format, if it's not already in UTF-8.
     *
     * @param string $Str input to convert to utf-8 format
     *
     * @return string
     */
    private static function makeUTF8($Str) {
        if ($Str != '') {
            if (self::isUTF8($Str)) {
                $Encoding = 'UTF-8';
            }
            if (empty($Encoding)) {
                $Encoding = mb_detect_encoding($Str, 'UTF-8, ISO-8859-1');
            }
            if (empty($Encoding)) {
                $Encoding = 'ISO-8859-1';
            }
            if ($Encoding == 'UTF-8') {
                return $Str;
            }
            else {
                return @mb_convert_encoding($Str, 'UTF-8', $Encoding);
            }
        }
        return $Str;
    }

    /**
     * Given a string, determine if that string is encoded in UTF-8 via regular expressions,
     * so that we don't have to rely on mb_detect_encoding which isn't quite as accurate
     *
     * @param string $Str input to check encoding of
     *
     * @return false|int
     */
    private static function isUTF8($Str) {
        return preg_match(
            '%^(?:
            [\x09\x0A\x0D\x20-\x7E]              // ASCII
            | [\xC2-\xDF][\x80-\xBF]             // non-overlong 2-byte
            | \xE0[\xA0-\xBF][\x80-\xBF]         // excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  // straight 3-byte
            | \xED[\x80-\x9F][\x80-\xBF]         // excluding surrogates
            | \xF0[\x90-\xBF][\x80-\xBF]{2}      // planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          // planes 4-15
            | \xF4[\x80-\x8F][\x80-\xBF]{2}      // plane 16
            )*$%xs',
            $Str
        );
    }
}
