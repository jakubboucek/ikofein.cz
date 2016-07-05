<?php
  define('PHP_NUM', substr(phpversion(), 0, 1));

  if (PHP_NUM < 5) {
    include './stuff/inc/tmpl/php4-info.php';
    die();
  }





  include './stuff/inc/mn-start.php';

  if (!file_exists('./' . $file['users']) && file_exists('./install.php')) {
    header('location: ./install.php');
    exit;
  }

  $auth = user_auth('0');





  if (isset($_GET['check-version']) || !isset($_COOKIE['mn_latest_version'])) {
    $latest_version = get_latest_version();
    if (!empty($latest_version)) {setcookie('mn_latest_version', $latest_version, time()+60*60*24*MN_VERSION_CHECK);}
  }
  else $latest_version = $_COOKIE['mn_latest_version'];


  if (!empty($latest_version) && (str_replace('.', '', $latest_version) > str_replace('.', '', MN_VERSION))) $info['new_version'] = true;
  else $info['new_version'] = false;





  if (isset($_COOKIE['mn_db_counts'])) $widget_counts = explode('|', $_COOKIE['mn_db_counts']);
  else $widget_counts = array(5, 5, 1);





  if (isset($_GET['access-denied'])) {
    overall_header($lang['index_access_denied'], $lang['index_access_denied'], 'error');
    echo '<p class="c">' . $lang['index_access_denied_text'] . '</p>';
  }





  elseif (isset($_GET['hide'])) {
    setcookie($_GET['hide'], true, time()+60*60*24*14);
  }





  elseif ($_SESSION['mn_user_auth'][0] == 3) {
    header('location: ./mn-profile.php');
    exit;
  }





  elseif (isset($_GET['action']) && $_GET['action'] == 'config') {
    $admin_tmpl['db_config'] = true;
  }





  elseif (isset($_POST['action']) && $_POST['action'] == 'config') {
  
    $w_info = (isset($_POST['widget_info']) && $_POST['widget_info'] == 'true') ? 1 : 0;
    $w_stats = (isset($_POST['widget_stats']) && $_POST['widget_stats'] == 'true') ? 1 : 0;
    $w_posts = (isset($_POST['widget_posts']) && $_POST['widget_posts'] == 'true') ? 1 : 0;
    $w_commets = (isset($_POST['widget_comments']) && $_POST['widget_comments'] == 'true') ? 1 : 0;
    $w_rss = (isset($_POST['widget_rss']) && $_POST['widget_rss'] == 'true') ? 1 : 0;
    
    setcookie('mn_db_widgets', $w_info . $w_stats . $w_posts . $w_commets . $w_rss, time()+60*60*24*365);
    setcookie('mn_db_counts', $_POST['posts_count'] . '|' . $_POST['comments_count'] . '|' . $_POST['rss_count'], time()+60*60*24*365);
    //setcookie('mn_db_posts', $_POST['posts_count'], time()+60*60*24*365);
    //setcookie('mn_db_comments', $_POST['comments_count'], time()+60*60*24*365);
    //setcookie('mn_db_rss', $_POST['rss_count'], time()+60*60*24*365);
    
    header('location: ./');
    exit;
  }





  elseif (isset($_GET['p']) && file_exists(MN_ROOT . $dir['posts'] . 'post_' . $_GET['p'] . '.php')) {
  
    $p = get_post_data($_GET['p']);
    
    if ($auth > 1 && $_SESSION['mn_user_id'] != $p['author']) {
      header('location: ./');
      exit;
    }
    
    $p['short_story'] = str_ireplace(array('[perm-link]', '[/perm-link]', '{%MN_URL%}'), array('', '', $conf['admin_url']), $p['short_story']);


    overall_header($lang['posts_post'] . ': ' . $p['title'], $lang['posts_post'] . ': <a href="./mn-posts.php?action=edit&amp;id=' . $p['id'] . '">' . $p['title'] . '</a>', 'main');
    echo '<div id="mn-post">';

    echo $p['short_story'] . str_ireplace('{%MN_URL%}', $conf['admin_url'], $p['full_story']);
    
    
    if (file_exists(MN_ROOT . $dir['comments'] . 'comments_' . $_GET['p'] . '.php')) {
      $c_file = file(MN_ROOT . $dir['comments'] . 'comments_' . $_GET['p'] . '.php');
      array_shift($c_file);
      $i = 1;
      
      echo '<div id="comments-title">' . $lang['posts_post_comments'] . '</div>';
      
      foreach ($c_file as $c_line) {
        $c_var = get_values('comments', $c_line, false);
        
        if ($c_var['status'] != 1) continue;
        else {
          echo '<div class="comment" id="c-' . $c_var['comment_id'] . '"><span class="info">[<a href="#c-' . $c_var['comment_id'] . '">' . $i . '</a>] <strong>' . $c_var['author_name'] . '</strong> ' . date('d.m.Y H:i', $c_var['timestamp']) . '<span class="links hide"> <a href="./mn-comments.php?action=reply&amp;post=' . $c_var['post_id'] . '&amp;id=' . $c_var['comment_id'] . '" class="fancy">' . $lang['comm_reply'] . '</a> | <a href="./mn-comments.php?action=edit&amp;post=' . $c_var['post_id'] . '&amp;id=' . $c_var['comment_id'] . '">' . $lang['uni_edit'] . '</a> | <a href="./mn-comments.php?a=m&amp;s=0&amp;f=' . $c_var['post_id'] .'&amp;c=' . $c_var['comment_id'] .'&amp;t=' . $_SESSION['mn_token'] . '&amp;from=post" class="ajaxcall">' . $lang['uni_delete'] . '</a></span></span><br />' . comment_format($c_var['comment_text']) . '</div>';
          $i++;
        }
      }
    }
    else echo '<div class="comment-info"><img src="./stuff/img/icons/information.png" alt="" /> ' . $lang['web_msg_no_comments'] . '</div>';
    
    if ($p['comments'] == 1 && $conf['comments']) {
      $post_id = $p['id'];
      include './stuff/inc/tmpl/comment-form.php';
    }
    
    
    echo '</div>';
  }





  else {
  
    if (file_exists(MN_ROOT . $file['posts'])) {
      $p_file = file(MN_ROOT . $file['posts']);
      $posts = array(); $timestamps = array(); $p_aprocess = 0;
      array_shift($p_file);

      foreach ($p_file as $p_line) {
        $post = get_values('posts', $p_line, false);

        if ($_SESSION['mn_user_auth'][1] > 1 && $post['author'] != $_SESSION['mn_user_id']) continue;
        elseif ($post['status'] == 4) $p_aprocess++;
        elseif (isset($_GET['mn_a']) && $post['status'] > '2') continue;
        else {
          $posts[$post['timestamp']] = $post['post_id'];
          $timestamps[$post['post_id']] = $post['timestamp']+$post['post_id'];
        }
      }
    }
    
    
    if (!empty($posts) && !empty($timestamps)) {
      $timestamps = mn_natcasesort($timestamps);
      $timestamps = array_reverse($timestamps, true);
      $posts_count = count($timestamps);
      $posts_result = ''; $i = 0;
      
      $i_max = (isset($widget_counts[0]) && is_numeric($widget_counts[0])) ? $widget_counts[0] : 10;
      $i_max = ($posts_count < $i_max) ? $posts_count : $i_max;

      foreach ($timestamps as $post_id => $timestamp) {
        $p = get_post_data($post_id);
        $p['title'] = (mb_strlen($p['title']) > 40) ? mb_substr($p['title'], 0, 40, 'utf-8') . '&hellip;' : $p['title'];
        $timestamp = ($p['timestamp'] == '9999999999') ? '-' : date('d.m.Y', $p['timestamp']);
        
        $posts_result .= '<tr><td class="date">' . $timestamp . '</td><td class="title"><a href="./?p=' . $p['id'] . '">' . $p['title'] . '</a></td><td class="edit"><a href="./mn-posts.php?action=edit&amp;id=' . $p['id'] . '" title="' . $lang['posts_edit_post'] . '" class="tooltip"><img src="./stuff/img/icons/edit-gray.png" alt="" /></a></td><td class="edit"><a href="./mn-posts.php?action=delete&amp;id=' . $p['id'] . '" title="' . $lang['posts_delete_post'] . '" class="fancy tooltip"><img src="./stuff/img/icons/cross-gray.png" alt="" /></a></td></tr>';
        $i++;
        
        if ($i == $i_max) break;
      }
      
      
      
      
      
      # COMMENTS
      $comments = array(); $comments_result = ''; $c_aprocess = 0;
      foreach ($posts as $p_timestamp => $p_id) {
        if (!file_exists($dir['comments'] . 'comments_' . $p_id . '.php')) continue;
        else {
          $c_file = file($dir['comments'] . 'comments_' . $p_id . '.php');
          array_shift($c_file);
          foreach ($c_file as $c_line) {
            $c_data = explode(DELIMITER, $c_line);
            if ($c_data[3] == 2) $c_aprocess++;
            elseif ($c_data[3] == 0) continue;
            $comments[] .= $c_line;
          }
        }
      }
      
      if (!empty($comments)) {
        $comments = mn_natcasesort($comments);
        $comments = array_reverse($comments);
        $comments_count = count($comments);

        $j_max = (isset($widget_counts[1]) && is_numeric($widget_counts[1])) ? $widget_counts[1] : 5;
        $j_max = ($comments_count < $j_max) ? $comments_count : $j_max;

        for ($j = 0; $j < $j_max; $j++) {
        
          $c = get_values('comments', $comments[$j], false);
          $cp = get_post_data($c['post_id']);
          $c['timestamp'] = $c['timestamp'] + ($conf['time_adj'] * 3600);
          
          if (mb_strlen($cp['title']) > 16) {
            $tooltip = ' title="' . $cp['title'] . '"';
            $cp['title'] = mb_substr($cp['title'], 0, 15, 'utf-8') . '&hellip;';
          }
          else {
            $cp['title'];
            $tooltip = '';
          }

          if (isset($widget_counts[1]) && $widget_counts[1] == 'new' && ($c['timestamp'] < $_SESSION['mn_last_login'] || empty($_SESSION['mn_last_login']))) continue;
          else $comments_result .= '<tr id="c' . $c['comment_id'] . '"><td class="c_author"><a href="./?p=' . $c['post_id'] . '#c-' . $c['comment_id'] . '">#</a> <strong>' . $c['author_name'] . '</strong><br />&nbsp;<span class="info hide comment_status"><a href="./?p=' . $c['post_id'] . '"' . $tooltip . '>' . $cp['title'] . '</a></span></td><td class="edit"><img src="./stuff/img/icons/information-gray.png" alt="" class="tooltip" title="<strong>' . $lang['uni_date'] . ':</strong> ' . date('d.m.Y H:i', $c['timestamp']) . '<br /><strong>' . $lang['comm_ip_address'] . ':</strong> ' . $c['ip_address'] . '<br /><strong>' . $lang['comm_host'] . ':</strong> ' . $c['host'] . '<br /><strong>' . $lang['comm_user_agent'] . ':</strong> ' . $c['user_agent'] . '" /><p><a href="./mn-comments.php?action=edit&amp;post=' . $c['post_id'] . '&amp;id=' . $c['comment_id'] . '" class="tooltip" title="' . $lang['uni_edit'] . '"><img src="./stuff/img/icons/edit-gray.png" alt="" /></a></p></td><td class="edit"><a href="./mn-comments.php?action=reply&amp;post=' . $c['post_id'] . '&amp;id=' . $c['comment_id'] . '" class="fancy tooltip" title="' . $lang['comm_reply'] . '"><img src="./stuff/img/icons/reply-gray.png" alt="" /></a><p><a href="./mn-comments.php?a=m&amp;s=0&amp;f=' . $c['post_id'] .'&amp;c=' . $c['comment_id'] .'&amp;t=' . $_SESSION['mn_token'] . '&amp;from=index" class="ajaxcall tooltip" title="' . $lang['uni_delete'] . '"><img src="./stuff/img/icons/cross-gray.png" alt="" /></a></p></td><td><div class="comment-text">' . comment_format($c['comment_text']) . '</div></td></tr>';
        }
      }


    }
    
    else {
      $posts_count = 0;
      $posts_result = '<tr><td colspan="4" class="c"><em>' . $lang['index_msg_no_posts'] . '</em></td></tr>';
    }
    



    if (isset($widget_counts[1]) && $widget_counts[1] == 'new' && empty($comments_result)) $comments_result = '<tr><td colspan="5" class="c"><em>' . $lang['index_msg_no_new_comments'] . '</em></td></tr>';
    elseif (empty($comments_result)) $comments_result = '<tr><td colspan="5" class="c"><em>' . $lang['index_msg_no_comments'] . '</em></td></tr>';
    
    if (empty($comments_count)) $comments_count = 0;
    
    
    
    $categories_count = 0;
    if (file_exists($file['categories'])) {
      $c_file = file($file['categories']);
      array_shift($c_file);
      foreach ($c_file as $c_line) {$categories_count++;}
    }
    
    
    $tags_count = 0;
    if (file_exists($file['tags'])) {
      $t_file = file($file['tags']);
      array_shift($t_file);
      foreach ($t_file as $t_line) {$tags_count++;}
    }
    
    
    $pages_count = 0;
    $page_dir = dir($dir['pages']);
    while ($page_file = $page_dir->read()) {
      if (!is_file($dir['pages'] . $page_file)) continue;
      else $pages_count++;
    }
    
    $files_count = 0;
    if (file_exists($file['files'])) {
      $f_file = file($file['files']);
      array_shift($f_file);
      foreach ($f_file as $f_line) {$files_count++;}
    }


    $users_count = 0;
    $u_file = file($file['users']);
    array_shift($u_file);
    foreach ($u_file as $u_line) {$users_count++;}
    
    
    $groups_count = 0;
    if (file_exists($file['groups'])) {
      $g_file = file($file['groups']);
      array_shift($g_file);
      foreach ($g_file as $g_line) {$groups_count++;}
    }


    $ips_count = count($banned_ips);
    
    
    
    
    
    overall_header($lang['index_dashboard'], $lang['index_welcome'] . ' <strong>' . $_SESSION['mn_user_name'] . '</strong>', 'main');
    
    $a = 1;
    
    if ($auth == 1 && (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][0] == 1)) {
    
      $warnings = array();
      clearstatcache();
      if (!is_writeable(MN_ROOT . 'data/')) $warnings[] = str_ireplace('%dir%', '"<span>/data/</span>"', $lang['index_chmod_check_dir']);
      foreach ($dir as $dir_name => $dir_path) {
        if (!is_writeable(MN_ROOT . $dir_path . '/')) $warnings[] = str_ireplace('%dir%', '"<span>/' . $dir_path . '</span>"', $lang['index_chmod_check_dir']);
      }
      foreach ($file as $file_name => $file_path) {
        if ($file_name == 'categories' || $file_name == 'banned_ips' || $file_name == 'galleries' || $file_name == 'last_backup' || $file_name == 'posts' || $file_name == 'tags' || $file_name == 'templates' || $file_name == 'templates_groups') continue;
        elseif ($file_name == 'files' && !file_exists(MN_ROOT . $file_path)) $warnings[] = str_ireplace(array('%file%','%files%'), array('"<span>/' . $file_path . '.php</span>"','<a href="./mn-uploads.php">' . $lang['uploads_files'] . '</a>.'), $lang['index_check_file_files_php']);
        elseif (!is_writeable(MN_ROOT . $file_path)) $warnings[] = str_ireplace('%file%', '"<span>/' . $file_path . '</span>"', $lang['index_chmod_check_file']);
      }


      $mnews_check_img = (empty($warnings)) ? '<img src="./stuff/img/icons/tick-gray.png" alt="" class="tooltip" title="' . $lang['index_info_mn_check_ok'] . '" />' : '<img src="./stuff/img/icons/cross-gray.png" alt="" class="tooltip" title="' . $lang['index_info_mn_check_no'] . '" />';
      $version_check_img = (!$info['new_version']) ? '<img src="./stuff/img/icons/tick-gray.png" alt="" class="tooltip" title="' . $lang['index_info_actuall_version'] . '" />' : '<img src="./stuff/img/icons/cross-gray.png" alt="" class="tooltip" title="' . $lang['index_info_new_version'] . ' MNews <strong>' . $latest_version . '</strong>" />';
      $php_check_img = (PHP_NUM >= 5) ? '<img src="./stuff/img/icons/tick-gray.png" alt="" class="tooltip" title="' . $lang['index_info_php_version_ok'] . '" />' : '<img src="./stuff/img/icons/warning.png" alt="" class="tooltip" title="' . $lang['index_info_php_version_warning'] . '" />';



      /****** Warnings - wrong CHMOD ******/
      if (!empty($warnings)) {
        echo '<div class="mn-warnings round">' . $lang['index_chmod_check_text1'] . '<ul>';
        foreach ($warnings as $warning) {
          echo '<li>' . $warning . '</li>';
        }
        echo '</ul><strong>' . $lang['index_chmod_check_text2'] . '</strong></div>';
      }



      /****** Warnings - wrong CHMOD ******/
      if ($info['new_version']) {
        echo '<div id="version-info" class="round">';
        echo '<strong>' . $lang['index_info_new_version'] . '</strong>';
        echo '<p class="j">' . str_replace(array('%old%','%new%','%url%'), array(MN_VERSION, '<strong>' . $latest_version . '</strong>', '<a href="http://mnewscms.com/">mnewscms.com</a>'), $lang['index_info_new_version_text']) . '</p>';
        //echo '<div class="simbutton"><a href="http://mnewscms.com/dowload.php?get=latest"><img src="./stuff/img/icons/control-next.png" alt="" /> Stiahnuť MNews 2.0.0</a></div>';
        echo '</div>';
      }



      /****** Integration info ******/
      if ($_SESSION['mn_user_id'] == 1 && $_SESSION['mn_registered'] > (time()-(60*60*24*7))) {
        if (!isset($_COOKIE['hide_conf_info'])) echo '<div class="db-info round" id="config-info"><span id="hide-config-info" class="simurl tooltip fr" title="' . $lang['index_dont_show_again'] . '">x</span><img src="./stuff/img/icons/information.png" alt="(i)" /> ' . str_ireplace('%config%', '<a href="./mn-config.php?t=2">' . $lang['config_config'] . '</a>', $lang['index_help_info_config']) . '</div>';
      
        if (!isset($_COOKIE['hide_int_info'])) {
          echo '<div class="db-info round" id="int-info"><span id="hide-int-info" class="simurl tooltip fr" title="' . $lang['index_dont_show_again'] . '">x</span><img src="./stuff/img/icons/information.png" alt="(i)" /> <strong>' . $lang['int_code_main'] . '</strong><p class="j">' . str_ireplace('%templates%', '<a href="./mn-templates.php">' . $lang['tmpl_templates'] . '</a>', $lang['int_code_main_help']) . '</p>';
          echo '<textarea class="integration" readonly="readonly">&lt;?php
  include \'' . dirname(__FILE__) . '/mn-show.php\';
?&gt;</textarea>';
          echo '<p class="j">' . str_ireplace('%integration%', '<a href="./mn-tools.php?action=integration">' . $lang['int_integration'] . '</a>', $lang['index_integration_text']) . '</p></div>';
        }
      }



      $mn_info = '';
      if (user_auth('1', true) == 1 && isset($p_aprocess) && $p_aprocess > 0) {
      
        if ($p_aprocess == 1) $msg_id = 1;
        elseif ($p_aprocess > 1 && $p_aprocess < 5) $msg_id = 2;
        else $msg_id = 3;
        
        $p_search = array('%n%', '%a%', '%/a%');
        $p_replace = array('<strong>' . $p_aprocess . '</strong>', '<a href="./mn-posts.php?s=4&amp;approve">', '</a>');
        $mn_info .= '<p><img src="./stuff/img/icons/information.png" alt="(i)" /> ' . str_ireplace($p_search, $p_replace, $lang['index_posts_approve' . $msg_id]) . '</p>';
      }
      
      if (user_auth('3', true) == 1 && isset($c_aprocess) && $c_aprocess > 0) {

        if ($c_aprocess == 1) $msg_id = 1;
        elseif ($c_aprocess > 1 && $c_aprocess < 5) $msg_id = 2;
        else $msg_id = 3;

        $c_search = array('%n%', '%a%', '%/a%');
        $c_replace = array('<strong>' . $c_aprocess . '</strong>', '<a href="./mn-comments.php?s=2&amp;approve">', '</a>');
        $mn_info .= '<p><img src="./stuff/img/icons/information.png" alt="(i)" /> ' . str_ireplace($c_search, $c_replace, $lang['index_comments_approve' . $msg_id]) . '</p>';
      }
      
      echo (!empty($mn_info)) ? '<div class="db-info round">' . $mn_info . '</div>' : '';



      echo '<table class="widget w' . $a . '"><thead><tr><th colspan="2">' . $lang['index_widget_info'] . '</th></tr></thead>';
      echo '<tr><td width="50%">' . $lang['index_info_system_check'] . '</td><td>' . $mnews_check_img . '</td></tr>';
      echo '<tr><td>' . $lang['index_info_mnews_version'] . '</td><td>' . MN_VERSION . ' ' . $version_check_img . '</td></tr>';
      echo '<tr><td>' . $lang['index_info_language'] . '</td><td><img src="./stuff/lang/lang_' . $conf['lang'] . '-gray.gif" alt="" /> ' . $languages[$conf['lang']] . ' (' . $conf['lang'] . ')</td></tr>';
      echo '<tr><td>' . $lang['index_info_php_version'] . '</td><td>' . phpversion() . ' ' . $php_check_img . '</td></tr>';
      echo '<tr><td>' . $lang['index_info_time'] . '</td><td>' . date('d.m.Y H:i') . '</td></tr>';
      echo '</table>';
      $a++;
    }
    
    if ($auth == 1 && (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][1] == 1)) {
      echo '<table class="widget w' . $a . '"><thead><tr><th colspan="3">' . $lang['index_widget_stats'] . '</th></tr></thead>';
      echo '<tr><td width="80%">' . $lang['index_stat_posts'] . '</td><td class="c"><a href="./mn-posts.php">' . $posts_count . '</a></td><td class="edit"><a href="./mn-posts.php?action=add" class="tooltip" title="' . $lang['posts_add_new_post'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_categories'] . '</td><td class="c"><a href="./mn-categories.php">' . $categories_count . '</a></td><td class="edit"><a href="./mn-categories.php" class="tooltip" title="' . $lang['cats_add_category'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_tags'] . '</td><td class="c"><a href="./mn-tags.php">' . $tags_count . '</a></td><td class="edit"><a href="./mn-tags.php" class="tooltip" title="' . $lang['tags_add_tag'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_comments'] . '</td><td class="c"><a href="./mn-comments.php">' . $comments_count . '</a></td><td class="edit">&nbsp;</td></tr>';
      echo '<tr><td>' . $lang['index_stat_pages'] . '</td><td class="c"><a href="./mn-pages.php">' . $pages_count . '</a></td><td class="edit"><a href="./mn-pages.php?action=add" class="tooltip" title="' . $lang['pages_add_page'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_files'] . '</td><td class="c"><a href="./mn-uploads.php">' . $files_count . '</a></td><td class="edit"><a href="./mn-uploads.php" class="tooltip" title="' . $lang['uploads_add_file'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_users'] . '</td><td class="c"><a href="./mn-users.php">' . $users_count . '</a></td><td class="edit"><a href="./mn-users.php?action=add" class="tooltip" title="' . $lang['users_add_user'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_groups'] . '</td><td class="c"><a href="./mn-groups.php">' . $groups_count . '</a></td><td class="edit"><a href="./mn-groups.php?action=add" class="tooltip" title="' . $lang['groups_add_group'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '<tr><td>' . $lang['index_stat_ips'] . '</td><td class="c"><a href="./mn-tools.php?action=ipban">' . $ips_count . '</a></td><td class="edit"><a href="./mn-tools.php?action=ipban" class="tooltip" title="' . $lang['ban_add_ip_address'] . '"><img src="./stuff/img/icons/add-gray.png" alt="" /></a></td></tr>';
      echo '</table>';
      $a++;
    }
    
    if ($_SESSION['mn_user_auth'][1] > 0 && (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][2] == 1)) {
      if (!empty($posts_result)) {
        echo '<table class="widget w' . $a . '"><thead><tr><th colspan="4">' . $lang['posts_last_posts'] . '</th></tr></thead>' . $posts_result . '</table>';
        $a++;
      }
      
    }
    
    if ($_SESSION['mn_user_auth'][3] > 0 && (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][3] == 1)) {
      if (!empty($comments_result)) {
        echo '<table class="widget w' . $a . '"><thead><tr><th colspan="5">' . $lang['comm_last_comments'] . '</th></tr></thead>' . $comments_result . '</table>';
        $a++;
      }
    }
    
    if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][4] == 1) {
      echo '<table class="widget w' . $a . '"><thead><tr><th>' . $lang['index_widget_rss'] . ' <a href="http://feeds.feedburner.com/mnews-cms" class="fr"><img src="./stuff/img/icons/rss.png" alt="rss" title="MNews RSS" /></a></th></tr></thead>' . mn_rss_reader(@$widget_counts[2]) . '</table>';
      $a++;
    }




    echo '<p class="cleaner">&nbsp;</p>';
    if (!isset($_GET['info'])) {
      $new_post_link = ($_SESSION['mn_user_auth'][1] > 0) ? '<a href="./mn-posts.php?action=add" id="db-post-link"><img src="./stuff/img/icons/add-gray.png" alt="" width="16" height="16" /> <span>' . $lang['posts_new_post'] . '</span></a>' : '';
      echo '<div id="db-links">' . $new_post_link . '<a href="./?action=config" id="db-config-link" class="fancy tooltip" title="' . $lang['index_config'] . '"><img src="./stuff/img/icons/config.png" alt="" /></a></div>';
    }


  }





  if (isset($admin_tmpl['db_config']) && $admin_tmpl['db_config']) {
?>
  <p class="c"><em><?php echo $lang['index_config'];?></em></p>

  <form action="./" method="post" id="db-config-form">
    <fieldset>
      <table>
        <?php if ($auth == 1) { ?>
        <tr><td colspan="2" class="l"><input type="checkbox" name="widget_info" id="widget_info" value="true"<?php if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][0] == 1) echo ' checked="checked"'; ?> /> <label for="widget_info"><?php echo $lang['index_widget_info'];?></label></td></tr>
        <tr><td colspan="2" class="l"><input type="checkbox" name="widget_stats" id="widget_stats" value="true"<?php if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][1] == 1) echo ' checked="checked"'; ?> /> <label for="widget_stats"><?php echo $lang['index_widget_stats'];?></label></td></tr>
        <?php } if ($_SESSION['mn_user_auth'][1] > 0) { ?>
        <tr>
          <td class="l"><input type="checkbox" name="widget_posts" id="widget_posts" value="true"<?php if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][2] == 1) echo ' checked="checked"'; ?> /> <label for="widget_posts"><?php echo $lang['index_widget_posts'];?></label></td>
          <td class="selects">
            <select name="posts_count" id="posts_count">
              <?php
                $conf_posts = (isset($widget_counts[0]) && is_numeric($widget_counts[0])) ? $widget_counts[0] : 10;
                for ($i = 1; $i <= 50; $i++) {
                  if ($i > 10 && ($i%5) != 0) continue;
                  else {
                    $sel = ($conf_posts == $i) ? ' selected="selected"' : '';
                    echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
                  }
                }
              ?>
            </select>
          </td>
        </tr>
        <?php } if ($_SESSION['mn_user_auth'][3] > 0) { ?>
        <tr>
          <td class="l"><input type="checkbox" name="widget_comments" id="widget_comments" value="true"<?php if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][3] == 1) echo ' checked="checked"'; ?> /> <label for="widget_comments"><?php echo $lang['index_widget_comments'];?></label></td>
          <td class="selects">
            <select name="comments_count" id="comments_count">
              <?php
                if ($widget_counts[1] == 'new') $conf_posts = ' selected="selected"';
                else $conf_posts = (isset($widget_counts[1]) && is_numeric($widget_counts[1])) ? $widget_counts[1] : 5;

                echo '<option value="new">' . $lang['index_comments_new'] . '</option>';
                for ($i = 1; $i <= 50; $i++) {
                  if ($i > 10 && ($i%5) != 0) continue;
                  else {
                    $sel = ($conf_posts == $i) ? ' selected="selected"' : '';
                    echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
                  }
                }
              ?>
            </select>
          </td>
        </tr>
        <?php } ?>
        <tr>
          <td class="l"><input type="checkbox" name="widget_rss" id="widget_rss" value="true"<?php if (!isset($_COOKIE['mn_db_widgets']) || $_COOKIE['mn_db_widgets'][4] == 1) echo ' checked="checked"'; ?> /> <label for="widget_rss"><?php echo $lang['index_widget_rss'];?></label></td>
          <td class="selects">
            <select name="rss_count" id="rss_count">
              <?php
                $conf_rss = (isset($widget_counts[2]) && is_numeric($widget_counts[2])) ? $widget_counts[2] : 2;
                for ($i = 1; $i <= 10; $i++) {
                  $sel = ($conf_rss == $i) ? ' selected="selected"' : '';
                  echo '<option value="' . $i . '"' . $sel . '>' . $i . '</option>';
                }
              ?>
            </select>
          </td>
        </tr>
      </table>


      <p class="c">
        <input type="hidden" name="action" value="config" />
        <button type="submit" name="submit"><img src="./stuff/img/icons/save-gray.png" alt="" /> <?php echo $lang['index_db_save'];?></button>
      </p>

    </fieldset>
  </form>

<?php
  die();
  }
  
  

  overall_footer();
?>