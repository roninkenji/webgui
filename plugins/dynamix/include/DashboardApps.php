<?PHP
/* Copyright 2005-2018, Lime Technology
 * Copyright 2014-2018, Guilherme Jardim, Eric Schultz, Jon Panozzo.
 * Copyright 2012-2018, Bergware International.
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */
?>
<?
$docroot = $docroot ?? $_SERVER['DOCUMENT_ROOT'] ?: '/usr/local/emhttp';
require_once "$docroot/webGui/include/Helpers.php";
require_once "$docroot/plugins/dynamix.docker.manager/include/DockerClient.php";
require_once "$docroot/plugins/dynamix.vm.manager/include/libvirt_helpers.php";

$display = $_POST['display'];
$menu = [];

if (pgrep('dockerd')!==false && ($display=='icons' || $display=='docker')) {
  $user_prefs = $dockerManPaths['user-prefs'];
  $DockerClient = new DockerClient();
  $DockerTemplates = new DockerTemplates();
  $containers = $DockerClient->getDockerContainers();
  $allInfo = $DockerTemplates->getAllInfo();

  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($containers as $ct) $sort[] = array_search($ct['Name'],$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$containers);
  }

  foreach ($containers as $ct) {
    $name = $ct['Name'];
    $id = $ct['Id'];
    $info = &$allInfo[$name];
    $running = $info['running'] ? 1:0;
    $paused = $info['paused'] ? 1:0;
    $is_autostart = $info['autostart'] ? 'true':'false';
    $updateStatus = $info['updated']=='true'||$info['updated']=='undef' ? 'true':'false';
    $template = $info['template'];
    $webGui = html_entity_decode($info['url']);
    $support = html_entity_decode($info['Support']);
    $project = html_entity_decode($info['Project']);
    $menu[] = sprintf("addDockerContainerContext('%s','%s','%s',%s,%s,%s,%s,'%s','%s','%s','%s');", addslashes($name), addslashes($ct['ImageId']), addslashes($template), $running, $paused, $updateStatus, $is_autostart, addslashes($webGui), $id, addslashes($support), addslashes($project));
    $shape = $running ? ($paused ? 'pause' : 'play') : 'square';
    $status = $running ? ($paused ? 'paused' : 'started') : 'stopped';
    $icon = $info['icon'] ?: '/plugins/dynamix.docker.manager/images/question.png';
    echo "<div class='Panel $status'>";
    echo "<div id='$id' style='display:block; cursor:pointer'>";
    echo "<div style='position:relative;width:48px;height:48px;margin:0px auto;'>";
    echo "<img src='$icon' class='$status' style='position:absolute;top:0;bottom:0;left:0;right:0;width:48px;height:48px;'><i class='fa iconstatus fa-$shape $status' title='$status'></i></div></div>";
    echo "<div class='PanelText'><span class='PanelText ".($updateStatus=='false'?'update':$status)."'>$name</span></div></div>";
  }
}

if (pgrep('libvirtd')!==false && ($display=='icons' || $display=='vms')) {
  $user_prefs = '/boot/config/plugins/dynamix.vm.manager/userprefs.cfg';
  $vms = $lv->get_domains();
  if (file_exists($user_prefs)) {
    $prefs = parse_ini_file($user_prefs); $sort = [];
    foreach ($vms as $vm) $sort[] = array_search($vm,$prefs) ?? 999;
    array_multisort($sort,SORT_NUMERIC,$vms);
  } else {
    natcasesort($vms);
  }
  foreach ($vms as $vm) {
    $res = $lv->get_domain_by_name($vm);
    $uuid = libvirt_domain_get_uuid_string($res);
    $dom = $lv->domain_get_info($res);
    $id = $lv->domain_get_id($res);
    $state = $lv->domain_state_translate($dom['state']);
    $vncport = $lv->domain_get_vnc_port($res);
    $vnc = '';
    if ($vncport > 0) {
      $wsport = $lv->domain_get_ws_port($res);
      $vnc = '/plugins/dynamix.vm.manager/vnc.html?autoconnect=true&host='.$_SERVER['HTTP_HOST'].'&port=&path=/wsproxy/'.$wsport.'/';
    } else {
      $vncport = ($vncport < 0) ? "auto" : "";
    }
    $template = $lv->_get_single_xpath_result($res, '//domain/metadata/*[local-name()=\'vmtemplate\']/@name');
    if (empty($template)) $template = 'Custom';
    $log = (is_file("/var/log/libvirt/qemu/$vm.log") ? "libvirt/qemu/$vm.log" : '');
    $menu[] = sprintf("addVMContext('%s','%s','%s','%s','%s','%s');", addslashes($vm), addslashes($uuid), addslashes($template), $state, addslashes($vnc), addslashes($log));
    $vmicon = $lv->domain_get_icon_url($res);
    echo renderVMContentIcon($uuid, $vm, $vmicon, $state);
  }
}
echo "\0".implode($menu);
