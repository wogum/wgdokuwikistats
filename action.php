<?php
/**
 * Creates simple statistics for pages showing count  
 *
 * @author Wojciech Guminski <wogum@poczta.fm>
 */
if(!defined('DOKU_INC')) die();
if(!defined('WGSTATS')) define ('WGSTATS',DOKU_PLUGIN.'wgdokuwikistats/'); 
require_once(DOKU_PLUGIN.'action.php');

class action_plugin_wgdokuwikistats extends DokuWiki_Action_Plugin 
{
  private $db;

  function __construct() 
    {
    if(!file_exists(WGSTATS.'.htwiki.db'))
      {
      $this->db = new PDO('sqlite:'.WGSTATS.'.htwiki.db');
      $sql='CREATE TABLE IF NOT EXISTS wikilog(id INTEGER PRIMARY KEY, page, action, user, ip, time, system, browser)';
      $this->db->exec($sql);
      $sql='CREATE INDEX IF NOT EXISTS wikipageidx ON wikilog(page)';
      $this->db->exec($sql);
      }
    else
      $this->db = new PDO('sqlite:'.WGSTATS.'.htwiki.db');
    }

  /**
   * Register its handlers with the DokuWiki's event controller
   */
  function register(&$controller) 
    {
    $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this,'wgcounter');
    $controller->register_hook('TPL_ACT_RENDER', 'AFTER', $this,'wgprintcounter');
    }

  /**
   * adds new data to stats table
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function wgcounter(&$event, $param)
    {
    global $ID;
    global $ACT;
    global $INFO;
    
    if($_SERVER['REQUEST_METHOD']!='GET' && $_SERVER['REQUEST_METHOD']!='POST') return;

    $agent = $_SERVER['HTTP_USER_AGENT'];
    $ip = $_SERVER['REMOTE_ADDR'];
    if(substr_count($ip,':')>0) //IPv6 -> long format IPv6
      {
      if(strpos($ip,'::')!==false)
        $ip=str_replace('::', str_repeat(':0', 8-substr_count($ip,':')).':', $ip);
      if(strpos($ip,':')===0) $ip='0'.$ip;
      $parts = explode(':',$ip);
      for($i=0; $i<8; $i++)
        while(strlen($parts[$i])<4)
          $parts[$i]='0'.$parts[$i];
      $ip=implode(':',$parts);
      }
    $system = $this->db->quote($this->getSystemName($agent));
    $browser = $this->db->quote($this->getBrowserName($agent));
    if(in_array($ACT,array('admin','login','logout','profile','register','resendpwd','index','recent','search','check')))
      {
      $page=$this->db->quote('~'.$ACT);
      $action=$this->db->quote($_REQUEST['page']);
      }
    else
      {
      $page=$this->db->quote($ID);
      $action=$this->db->quote($ACT);
      }
    if($INFO['client']==$_SERVER['REMOTE_ADDR'])
      $user=$this->db->quote('');
    else
      $user=$this->db->quote($INFO['client']);
    //save
    $sql = 'INSERT INTO wikilog(page,action,user,ip,time,system,browser) VALUES(';
    $sql .= $page.','.$action.','.$user.',';
    $sql .= $this->db->quote($ip).",'".date('Y-m-d H:i:s')."',";
    $sql .= $system.','.$browser.");";
    $this->db->exec($sql);
    }

  /**
   * prints conter of the ID page
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function wgprintcounter(&$event, $param)
    {
    global $ID;
    global $ACT;

    if($ACT != 'show') return;
    if(!$this->getConf('wgcountershow')) return;

    $sql='SELECT COUNT(*) FROM wikilog WHERE page='.$this->db->quote($ID);
    $res = $this->db->query($sql);
    if($res) 
      {
      $row = $res->fetch();
      print(PHP_EOL.'<div class="wgcounter"><a href="'.wl('','do=admin,page='.$this->getPluginName()).'">['.intval($row[0]).']</a></div>'.PHP_EOL);
      }
    }

  /**
   * gets system name from HTTP_USER_AGENT
   *
   * @input HTTP_USER_AGENT string
   * @output Short operating system name 
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function getSystemName($agent)
    {
    if(strpos($agent,'Symb')>0) $system='Symbian';
    else if(strpos($agent,'Android')>0) $system='Android';
    else if(strpos($agent,'iPhone')>0) $system='iPhone Mac OS';
    else if(strpos($agent,'iPod')>0) $system='iPod Mac OS';
    else if(strpos($agent,'Windows NT 10')>0) $system='Windows 10';
    else if(strpos($agent,'Windows NT 6.3')>0) $system='Windows 8.1';
    else if(strpos($agent,'Windows NT 6.2')>0) $system='Windows 8';
    else if(strpos($agent,'Windows NT 6.1')>0) $system='Windows 7';
    else if(strpos($agent,'Windows NT 6.0')>0) $system='Windows Vista';
    else if(strpos($agent,'Windows NT 5.2')>0) $system='Windows 2003';
    else if(strpos($agent,'Windows NT 5.1')>0) $system='Windows XP';
    else if(strpos($agent,'Windows NT 5.0')>0) $system='Windows 2000';
    else if(strpos($agent,'Windows NT 4')>0) $system='Windows NT 4';
    else if(strpos($agent,'Windows NT 3')>0) $system='Windows NT 3';
    else if(strpos($agent,'Windows 98')>0) $system='Windows 98';
    else if(strpos($agent,'Windows 95')>0) $system='Windows 95';
    else if(strpos($agent,'Windows CE')>0) $system='Windows CE';
    else if(strpos($agent,'Win')>0) $system='Windows';
    else if(strpos($agent,'Mac')>0) $system='Mac OS';
    else if(preg_match('/(Linux)|(Unix)|(Dillo)|(BSD)/i',$agent)) $system='Linux';
    else $system='Unknown';
    return $system;
    }

  /**
   * gets pupular browser name from HTTP_USER_AGENT
   *
   * @input HTTP_USER_AGENT string
   * @output Short brower name with version number
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function getBrowserName($agent)
    {
    if(strpos($agent,'Edge')>0)
      {
      $browser = 'Edge ';
      if(preg_match('/Edge.([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'Firefox')>0)
      {
      $browser = 'Firefox ';
      if(preg_match('/Firefox.([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'Chrome')>0)
      {
      $browser = 'Chrome ';
      if(preg_match('/Chrome.([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'Opera')>0)
      {
      $browser = 'Opera ';
      if(preg_match('/Opera.+Version.([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'MSIE')>0)
      {
      $browser = 'Internet Explorer ';
      if(preg_match('/MSIE.([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'Trident')>0)
      {
      $browser = 'Internet Explorer ';
      if(preg_match('/Win.+Trident.+rv:([0-9]+)/i',$agent,$matches)) $browser .= $matches[1];
      }
    else if(strpos($agent,'Iceweasel')>0) $browser='Firefox';
    else if(strpos($agent,'Minefield')>0) $browser='Firefox';
    else if(strpos($agent,'Flock')>0) $browser='Firefox';
    else if(strpos($agent,'Konqeror')>0) $browser='Konqueror';
    else if(stripos($agent,'Links')>0) $browser='Links';
    else if(stripos($agent,'Lynx')>0) $browser='Lynx';
    else if(strpos($agent,'wget')>0) $browser='wget';
    else if(strpos($agent,'NokiaBrowser')>0) $browser='NokiaBrowser';
    else if(strpos($agent,'Safari')>0) $browser='Safari';
    else $browser='Unknown';
    return $browser;
    }

}