<?php /**
 * autoRedirector plugin
 * @version 0.1 (2016-02-07)
 * 
 *
 * @category plugin
 * @license http://www.gnu.org/copyleft/gpl.html GNU Public License (GPL)
 * 
 * @internal @properties &table_name=Table Name;text;autoredirector &check_table=Check Table;list;yes,no;yes &maxCount=max count rows;text;20
 * @internal @events OnBeforeDocFormSave,OnDocFormSave,OnPageNotFound,OnEmptyTrash
 * 
 * @link https://github.com/korobkovandrey/evoAutoRedirector
 * 
 * @copyright 2016
*/

global $ar_cleanURIs, $ar_parent_old, $ar_alias_old;

$e = $modx->event;

$maxCount = isset($e->params['maxCount']) && !empty($e->params['maxCount']) ? $e->params['table_name'] : 20;
$table_name = isset($e->params['table_name']) && !empty($e->params['table_name']) ? $e->params['table_name'] : 'autoredirector';
$maxCount = intval($maxCount);
$table_name = trim($table_name);
$ar_table = $modx->getFullTableName($table_name);

if(!function_exists('ar_check_install')){
    function ar_check_install($ar_table){
        global $modx;
        $status = $modx->db->getRow($modx->db->query("CHECK TABLE " . $ar_table));
        if($status['Msg_type'] == 'Error'){
            $ar_install_sql = "CREATE TABLE " . $ar_table . " (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `uri` varchar(255),
  `content_id` int(10) NOT NULL,
  `createdon` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  INDEX `uri` (`uri` ASC),
  INDEX `content_id` (`content_id` ASC)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
            $modx->db->query($ar_install_sql);
			$modx->logEvent(1, 1, 'Table ' . $ar_table . 'created');
        }
    }
}

if(!function_exists('ar_clean_uri')){
    function ar_clean_uri($uri){
        global $modx;
        $q_url_prefix = preg_quote($modx->config['friendly_url_prefix']);
        $q_url_suffix = preg_quote($modx->config['friendly_url_suffix']);
        $uri = preg_split("/\?|\&/", $uri);
        $uri = trim(trim(array_shift($uri), '/'));
        $uri = preg_replace("/^" . $q_url_prefix . "/i", '', $uri);
        $uri = preg_replace("/" . $q_url_suffix . "$/i", '', $uri);
        $uri = strtolower(trim($uri, '/'));
        return $uri;
    }
}

switch ($e->name) {
    case 'OnBeforeDocFormSave':
        if($e->params['mode'] != 'upd'){
            break;
        }
		if(isset($e->params['check_table']) && $e->params['check_table'] == 'yes'){
			ar_check_install($ar_table);
		}
        $id = intval($e->params['id']);
        $res = $modx->db->select('alias, parent', $modx->getFullTableName('site_content'), "id=" . $id);
        $row = $modx->db->getRow($res);
        if(!$row){
            break;
        }
        $ar_parent_old = $row["parent"];
        $ar_alias_old = $row["alias"];
        $ids = $modx->getChildIds($id, 20, array($id));
        $ar_cleanURIs = array();
        if($modx->config['friendly_urls'] == 1 && $modx->config['friendly_alias_urls'] == 1){
            foreach($ids as $v){
                $ar_cleanURIs[$v] = ar_clean_uri($modx->makeUrl($v));
            }
        }
        break;
    case 'OnDocFormSave':
        if($e->params['mode'] != 'upd'){
            break;
        }
        $id = intval($e->params['id']);
        $res = $modx->db->select('alias, parent', $modx->getFullTableName('site_content'), "id=" . $id);
        $row = $modx->db->getRow($res);
        if(!$row){
            break;
        }
        $ar_parent = $row["parent"];
        $ar_alias = $row["alias"];
        if(($ar_parent == $ar_parent_old && $ar_alias == $ar_alias_old) || !is_array($ar_cleanURIs)){
            break;
        }
        foreach($ar_cleanURIs as $k => $v){
            if(is_numeric($v)){
                continue;
            }
            $modx->db->insert(array(
                'uri' => $modx->db->escape($v),
                'content_id' => $k
            ), $ar_table);
            $count = $modx->db->getValue($modx->db->select("count(*)", $ar_table, "content_id = " . $k));
            if($count > $maxCount){
                $modx->db->delete($ar_table, "content_id = " . $k, "id ASC", $count - $maxCount);
            }
        }
        break;
    case 'OnPageNotFound':
        $ar_clean_uri = $modx->db->escape(ar_clean_uri($modx->virtualDir . "/" . $modx->documentIdentifier));
        $res = $modx->db->select("content_id", $ar_table, "uri = '" . $ar_clean_uri . "'", "id DESC", 1);
        if($modx->db->getRecordCount($res)){
            $doc_id = $modx->db->getValue($res);
            $url = $modx->makeUrl($doc_id);
            $modx->sendRedirect($url, 0, '', 'HTTP/1.1 301 Moved Permanently');
        }
        break;
    case 'OnEmptyTrash':
        if(!isset($e->params['ids']) || empty($e->params['ids'])){
            break;
        }
        $ids = $e->params['ids'];
        $modx->db->delete($ar_table, "content_id IN (" . implode(",", $ids) . ")");
        break;
}?>
