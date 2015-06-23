<?php
/**
 * Creates simple statistics for pages showing count  
 *
 * @author Wojciech Guminski <wogum@poczta.fm>
 */
if(!defined('DOKU_INC')) die();
if(!defined('WGSTATS')) define ('WGSTATS',DOKU_PLUGIN.'wgdokuwikistats/'); 
require_once(DOKU_PLUGIN.'admin.php');

class admin_plugin_wgdokuwikistats extends DokuWiki_Admin_Plugin 
{
  private $db;
  private $fromdate, $todate, $page, $action, $user, $ip;
  private $where;

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

  function forAdminOnly()
    {
    return false;
    }

  /**
   * handle user request for admin page
   */
  function handle() 
    { 
    if(isset($_POST['wgfrom']) && preg_match('/^[0-9]{4}(-[0-9]{2}){2}$/',$_POST['wgfrom'])) 
      $this->fromdate = $_POST['wgfrom'];
    else 
      //$this->fromdate =  $this->getSQLVal('SELECT SUBSTR(MIN(time),1,10) FROM wikicounter');
      $this->fromdate = date('Y-m-d', mktime(0,0,0,date('m')-1,1));    
    if(isset($_POST['wgto']) && preg_match('/^[0-9]{4}(-[0-9]{2}){2}$/',$_POST['wgto'])) 
      $this->todate = $_POST['wgto'];
    else 
      //$this->todate =  $this->getSQLVal('SELECT SUBSTR(MAX(time),1,10) FROM wikicounter');
      $this->todate = date('Y-m-d',time());
    if(isset($_POST['wgpage']))
      $this->page = $_POST['wgpage'];
    else
      $this->page = '';
    if(isset($_POST['wgact']))
      $this->action = $_POST['wgact'];
    else
      $this->action = '';
    if(isset($_POST['wguser']))
      $this->user = $_POST['wguser'];
    else
      $this->user = '';
    if(isset($_POST['wgip']))
      $this->ip = $_POST['wgip'];
    else
      $this->ip = '';
    $this->where = ' WHERE time>='.$this->db->quote($this->fromdate.' 00:00:00');
    $this->where .= ' AND time<='.$this->db->quote($this->todate.' 23:59:59');
    if(!empty($this->page))
      $this->where .= ' AND page='.$this->db->quote($this->page);
    if(!empty($this->action))
      $this->where .= ' AND action='.$this->db->quote($this->action);
    if(!empty($this->user))
      $this->where .= ' AND user='.$this->db->quote($this->user);
    if(!empty($this->ip))
      $this->where .= ' AND ip='.$this->db->quote($this->ip);
    }

  /**
   * output appropriate html for admin page
   */
  function html() 
    {
    global $ID;

    print('<h1>'.$this->getLang('menu').'</h1>'.PHP_EOL);

    $limit = intval($this->getConf('wgfilterlen'));
    if($limit>0)
      {
      print('<div class="wgleft"><form action="'.wl($ID).'" method="post">'.PHP_EOL);
      // output hidden values to ensure dokuwiki will return back to this plugin
      print(' <input type="hidden" name="do" value="admin" />'.PHP_EOL);
      print(' <input type="hidden" name="page" value="'.$this->getPluginName().'" />'.PHP_EOL);
  	  print(' <input type="hidden" name="id" value="'.$ID.'" />'.PHP_EOL);
      formSecurityToken();

      print('<p><b>'.$this->getLang('filter').'</b>'.PHP_EOL);
      print(' &nbsp; '.$this->getLang('fromdate'));
      print('<input type="date" name="wgfrom" value="'.$this->fromdate.'" />'.PHP_EOL);
      print(' &nbsp; '.$this->getLang('todate'));
      print('<input type="date" name="wgto" value="'.$this->todate.'" /><br />'.PHP_EOL);

      $sql='SELECT DISTINCT(page) FROM wikilog ORDER BY 1 LIMIT '.$limit;
      $this->getSelectInput('wgpage',$sql,$this->page);
      $sql='SELECT DISTINCT(action) FROM wikilog ORDER BY 1 LIMIT '.$limit;
      $this->getSelectInput('wgact',$sql,$this->action);
      $sql='SELECT DISTINCT(user) FROM wikilog ORDER BY 1 LIMIT '.$limit;
      $this->getSelectInput('wguser',$sql,$this->user);
      $sql='SELECT DISTINCT(ip) FROM wikilog ORDER BY 1 LIMIT '.$limit;
      $this->getSelectInput('wgip',$sql,$this->ip);

      print('<br />'.PHP_EOL);
      print('<input type="submit" class="button" value="'.$this->getLang('submit').'" />'.PHP_EOL);
      print('</p></form></div><div style="clear:both"></div>'.PHP_EOL);
    }

    if($this->getConf('wgstatssort')) $sort='2 DESC '; else $sort='1 ASC ';

    foreach(array('page', 'user', 'ip', 'browser', 'system') as $val)
      {
      $limit = intval($this->getConf('wgstats'.$val));
      if($limit>0)
        {
        if($val=='ip') $val='SUBSTR(ip,1,19)';
        print('<div class="wgleft"><h2>'.$this->getLang('stats'.$val).'</h2>'.PHP_EOL);
        $sql="SELECT $val, COUNT(*) AS count FROM wikilog $this->where GROUP BY $val ORDER BY $sort LIMIT $limit";
        $this->getSQLTable($sql);
        print('</div>'.PHP_EOL);
        }
      }

    $limit = intval($this->getConf('wgstatslog'));
    if($limit>0)
      {
      print('<div style="clear:both"></div><div><h2>'.$this->getLang('statslog').'</h2>'.PHP_EOL);
      $sql="SELECT id,time,page,action,user,ip,system,browser FROM wikilog $this->where ORDER BY id DESC LIMIT $limit";
      $this->getSQLTable($sql);
      print('</div>'.PHP_EOL);
      }

    }

  /**
   * prints SELECT for form
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */     
  function getSelectInput($wgid,$sql,$def='')
    {
    print(' &nbsp; '.$this->getLang($wgid));
    print('<select name="'.$wgid.'">'.PHP_EOL);
    print(' <option value=""></option>'.PHP_EOL);
    $res=$this->db->query($sql);
    if($res)
      {
      while($row=$res->fetch(PDO::FETCH_NUM))
        {
        if(!empty($row[0]))
          {
          print(' <option value="'.$row[0].'"');
          if($row[0]==$def) print(' selected="selected"');
          print('>'.$row[0].'</option>'.PHP_EOL);
          }
        }
      }
    print('</select>'.PHP_EOL);
    }

  /**
   * prints HTML table from SQL query
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function getSQLTable($sql)
    {
    $res=$this->db->query($sql);
    if($res && $row=$res->fetch(PDO::FETCH_NUM))
      {
      print(PHP_EOL.'<div class="centeralign"><table>'.PHP_EOL);
      print(' <tr><th class="centeralign">#</th>');
      for($i=0; $i<$res->columnCount(); $i++)
        {
        $cm=$res->getColumnMeta($i);
        print('<th>'.$this->getLang($cm['name']).'</th>');
        }
      print('</tr>'.PHP_EOL);
      $k=0;
      do
        {
        $k++;
        print(' <tr><td><small>'.$k.'</small></td>');
        for($i=0; $i<$res->columnCount(); $i++)
          print('<td>'.$row[$i].'</td>');
        print('</tr>'.PHP_EOL);
        }while($row=$res->fetch(PDO::FETCH_NUM));
      print '</table></div>'.PHP_EOL;
      }
    }

  /**
   * gets one SQL value
   *
   * @author Wojciech Guminski <wogum@poczta.fm>
   */
  function getSQLVal($sql)
    {
    $res=$this->db->query($sql);
    if($res)
      $row=$res->fetch(PDO::FETCH_NUM);
    else
      $row=array('');
    $res->closeCursor();
    return $row[0];
    }
}
