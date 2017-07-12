<?php
/*
Plugin Name: VKontakte Wall Post
Plugin URI: http://www.devstan.com/vkontakte-wall-post/
Description: The plugin allows you to publish the text and links on your WordPress posts to your personal VKontakte Wall or Group Wall where your are administrator. Share your posts and pages with your VKontakte social friends or with members of your group.
Author: RudeStan
Version: 2.0
Author URI: http://www.devstan.com
*/

define('VK_TEXT_DOMAIN', 'vkwall-post');
load_plugin_textdomain(VK_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)));

$Posting  = new vkWallPost();
$Settings = new vkWallPostSettings();

class vkWallPost {
    function __construct() {
        add_action('post_submitbox_start', array($this,'renderPostButton'));
        add_action('admin_notices',array($this,'openApiJsFunctions'),1);
        add_action('admin_notices',array($this,'publishToWall'),99);
        add_action('publish_post', array($this,'setCheckbox'));
        add_action('publish_page', array($this,'setCheckbox'));
    }

    function checkboxCode() {
        global $post;
        $img_url =  WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__))."/vk_logo.png";
        echo '<div style="margin:8px 0px 16px 0px;padding-bottom:16px;">';


        echo '<div style="clear:both;"><div style="float:left;"><label for="vkwall_post"><img src="'.$img_url.'" style="vertical-align: middle;margin: 0px 4px 0px 0px;">'.__("Publish to Vkontakte wall",VK_TEXT_DOMAIN).'</label></div><div style="float:right;"><input type="checkbox" style="min-width:10px;" id="vkwall_post" name="vkwall_post" style="margin:0;padding:0;" value="1"';
        if(strlen(get_option('vkwallpost_api_id')) > 0) {
            if($post->filter !== "edit") {
                if(get_option('vkwallpost_is_group') == 1 && strlen(get_option('vkwallpost_group_id')) <= 0 ) {
                    echo ' disabled="disabled"></div></div>';
                    echo __("<div style=\"clear:both;padding-top:8px;\"><em style=\"color:#af0000;\">No <b>Group ID</b> specified!<br/>Please check the <a href=\"options-general.php?page=",VK_TEXT_DOMAIN);
                    echo plugin_basename(__FILE__);
                    echo __("\">plugin settings</a>.</em></div>",VK_TEXT_DOMAIN);
                } else {
                    echo get_option('vkwallpost_default_ischecked') ? ' checked="checked"' : '';
                    echo '></div></div>';
                }
            } else {
                if(get_option('vkwallpost_is_group') == 1 && strlen(get_option('vkwallpost_group_id')) <= 0 ) {
                    echo ' disabled="disabled"></div></div>';
                    echo __("<div style=\"clear:both;padding-top:8px;\"><em style=\"color:#af0000;\">No <b>Group ID</b> specified!<br/>Please check the <a href=\"options-general.php?page=",VK_TEXT_DOMAIN);
                    echo plugin_basename(__FILE__);
                    echo __("\">plugin settings</a>.</em></div>",VK_TEXT_DOMAIN);
                } else
                    echo '></div></div>';
            }
        } else {
            echo ' disabled="disabled"></div></div>';
            echo __("<div style=\"clear:both;padding-top:8px;\"><em style=\"color:#af0000;\">No <b>API ID</b> specified!<br/>Please check the <a href=\"options-general.php?page=",VK_TEXT_DOMAIN);
            echo plugin_basename(__FILE__);
            echo __("\">plugin settings</a>.</em></div>",VK_TEXT_DOMAIN);
        }


        echo '</div>';
    }

    function renderPostButton() {
        global $post;
        if($post->post_type == "page") { // post | page
            if(get_option('vkwallpost_post_pages')) {
                $this->checkboxCode();
            }
        } else {
            $this->checkboxCode();
        }
    }

    function setCheckbox($post_ID) {
        add_post_meta($post_ID, "vkwall_post", isset($_POST['vkwall_post']) ? 1:0);
        return $post_ID;
    }

    function publishToWall() {
        global $post;
        $vkwall_post = get_post_meta($post->ID, "vkwall_post", true);
        delete_post_meta($post->ID, "vkwall_post");
        if($post->post_status == "publish" && $vkwall_post && strlen(get_option('vkwallpost_api_id')) > 0) {
            $msg = $this->prepearMessage();
            $this->openApiCallPostMessage($msg);
        }
    }

    function cutText($text) {
        $text = strip_tags($text);
        $limit = get_option('vkwallpost_symbols_count');
        if($limit > 0 && mb_strlen($text,"UTF-8") > $limit) {
            $tmp_text = trim(mb_substr($text, 0, $limit,"UTF-8"));
            $p_space = mb_strrpos($tmp_text, " ","UTF-8");
            $p_dot   = mb_strrpos($tmp_text, ".","UTF-8");
            $end_dot = false;
            if($p_space !== false || $p_dot !== false) {
            $end_substr = $p_space;
            if($p_dot > $p_space) {
                $end_substr = $p_dot+1;
                $end_dot = true;
            }
            $tmp_text = mb_substr($tmp_text, 0, $end_substr,"UTF-8");
            }
            if(!$end_dot)
            $tmp_text.="...";
            return $tmp_text;
        }
        return $text;
    }


    function prepearMessage() {
        global $post;
        $wp_post = get_post($post->ID); // getting post
        $msg = get_option('vkwallpost_template');
        if(strlen($msg) > 0) {
            $msg = str_ireplace(
                array("#url#","#permalink#","#title#","#post#","#date#","#time#"),
                array($wp_post->guid,
                              get_permalink($wp_post->ID),
                  $wp_post->post_title,
                  self::cutText($wp_post->post_content),
                  date("d.m.Y",strtotime($wp_post->post_date)),
                  date("H:i",strtotime($wp_post->post_date))), $msg);
            $msg = str_ireplace(array("'","\n","\r"),array("\'"," ",""),$msg);
        }
        return $msg;
    }

    function openApiCallPostMessage($msg) { ?>
		<script type="text/javascript">postMessage('<?=$msg;?>');</script>
<?php  }


    function openApiJsFunctions() {
?>
	<script src="http://vk.com/js/api/openapi.js" type="text/javascript"></script>
	<script language="javascript">
	var should_repost = 0;
	var msg = "";

<?php if(get_option('vkwallpost_api_id')) { ?>
    VK.init({apiId: '<?=get_option('vkwallpost_api_id');?>'});
<?php } ?>

	function authInfo(response) {
	    if (response.session) {
            if(should_repost == 1) {
                postMessage();
            }
	    } else {
            console.log("Not authorized!");
	    }
	}

    function reAuth(r) {
        if(r.response) {
            msg = "";
        } else {
            if(r.error.error_code == '10008' && !should_repost) {
                should_repost = 1;
                VK.Auth.login(authInfo);
            }
        }
    }

	function postMessage(_msg) {
	    if(msg.length == 0) {
            msg = _msg;
	    }
	    VK.Api.call('wall.post', { <?php
            $apiArgs = array();
            $apiArgs[] = 'message: msg';

            if(get_option('vkwallpost_is_group') == 1 && get_option('vkwallpost_group_id')) {
                $apiArgs[] = 'owner_id: -' .get_option('vkwallpost_group_id');
                $apiArgs[] = get_option('vkwallpost_publish_fromgroup') ? 'from_group: 1' : 'from_group: 0';
            }

            if(get_option('vkwallpost_friends_only'))
                $apiArgs[] = 'friends_only: 1';

            if(get_option('vkwallpost_attach_link'))
                $apiArgs[] = 'attachments: \''.get_permalink($wp_post->ID).'\'';

            print(implode(", ",$apiArgs));
            ?> }, function(r) { reAuth(r); });
	}
	</script>
<?php
    }

}


class vkWallPostSettings {

    function __construct() {
        register_activation_hook( __FILE__, array($this, 'installPlugin'));
        register_deactivation_hook( __FILE__, array($this, 'uninstallPlugin'));
        add_action('admin_menu', array($this,'vkwall_admin_menu_action'));
        add_filter('plugin_action_links', array($this,'add_settings_link'), 10, 2 );
        if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['saveopts'])) {
            $this->updateOptions();
        }
    }

    function installPlugin() {
        add_option('vkwallpost_api_id','', '','no');
        add_option('vkwallpost_template','#title# #post#', '','no');
        add_option('vkwallpost_default_ischecked','1', '','no');
        add_option('vkwallpost_post_pages','0', '','no');
        add_option('vkwallpost_symbols_count','0','','no');
        add_option('vkwallpost_is_group', '0', '', 'no');    // radio button if is group
        add_option('vkwallpost_group_id', '', '', 'no');     // id of group
        add_option('vkwallpost_publish_fromgroup', '1', '', 'no');// publish from group
        add_option('vkwallpost_attach_link', '1', '', 'no'); // should the link on post be attached
        add_option('vkwallpost_friends_only', '0', '', 'no'); // visible to friends only
    }

    function uninstallPlugin() {
        delete_option('vkwallpost_api_id');
        delete_option('vkwallpost_template');
        delete_option('vkwallpost_default_ischecked');
        delete_option('vkwallpost_post_pages');
        delete_option('vkwallpost_symbols_count');
        delete_option('vkwallpost_is_group');
        delete_option('vkwallpost_group_id');
        delete_option('vkwallpost_publish_fromgroup');
        delete_option('vkwallpost_attach_link');
        delete_option('vkwallpost_friends_only');
    }

    function updateOptions() {
        update_option('vkwallpost_api_id',$_POST['vkwallpost_api_id']);
        update_option('vkwallpost_template',$_POST['vkwallpost_template']);
        update_option('vkwallpost_default_ischecked',isset($_POST['vkwallpost_default_ischecked']) ? 1 : 0);
        update_option('vkwallpost_post_pages',isset($_POST['vkwallpost_post_pages']) ? 1 : 0);
        update_option('vkwallpost_symbols_count',$_POST['vkwallpost_symbols_count']);
        update_option('vkwallpost_is_group', $_POST['vkwallpost_is_group']);
        update_option('vkwallpost_group_id', $_POST['vkwallpost_group_id']);
        update_option('vkwallpost_publish_fromgroup', isset($_POST['vkwallpost_publish_fromgroup']) ? 1 : 0);
        update_option('vkwallpost_attach_link', isset($_POST['vkwallpost_attach_link']) ? 1 : 0);
        update_option('vkwallpost_friends_only', isset($_POST['vkwallpost_friend_only']) ? 1 : 0);
    }

    function add_settings_link($links, $file) {
        if ($file == plugin_basename(__FILE__)){
            $settings_link = '<a href="options-general.php?page='.plugin_basename(__FILE__).'">'.__("Settings", VK_TEXT_DOMAIN).'</a>';
            array_unshift($links, $settings_link);
        }
        return $links;
    }

    function vkwall_admin_menu_action() {
        $page = add_options_page(__('Settings', VK_TEXT_DOMAIN), __('VKontakte Wall Post', VK_TEXT_DOMAIN), 'manage_options', __FILE__, array($this, 'vk_wall_render_options_page'));
    }

    function vk_wall_render_options_page() {
?>
    <script language="javascript">
            function setGroupBlockActive() {
                jQuery('#group-block').attr('style','border:1px #8c5a12 solid;background-color:#FFFFE0;');
                jQuery('#table_group').show();
            }

            function setGroupBlockInActive() {
                jQuery('#group-block').attr('style','');
                jQuery('#table_group').hide();
            }

            jQuery(function() {
               jQuery('#isGroupWall').bind('click', function(){
                   setGroupBlockActive();
               });

               jQuery('#isOwnWall').bind('click', function(){
                   setGroupBlockInActive();
               });

               jQuery('#vkwallpost_api_id').bind('keyup keydown', function() {
                   if(jQuery('#vkwallpost_api_id').val().length)
                       jQuery('#vkwallpost_api_id').attr('style','width:100px;border: 2px #31ac28 solid;');
                   else
                       jQuery('#vkwallpost_api_id').attr('style','width:100px;border: 2px #af0000 solid;');
               });

               jQuery('#vkwallpost_group_id').bind('keyup keydown', function() {
                   if(jQuery('#vkwallpost_group_id').val().length) {
                       jQuery('#vkwallpost_group_id').attr('style','width:100px;border: 2px #31ac28 solid;');
                       jQuery('#vkwallpost_publish_fromgroup').attr('disabled',false);
                   } else {
                       jQuery('#vkwallpost_group_id').attr('style','width:100px;border: 2px #af0000 solid;');
                       jQuery('#vkwallpost_publish_fromgroup').attr('disabled',true);
                   }
               });

               jQuery('#vkwallpost_group_id').keyup();
               jQuery('#vkwallpost_api_id').keyup();

            });

    </script>

	<div class="wrap">
	<div class="icon32" id="icon-options-general"><br /></div>
	<h2>VKontakte Wall Post <?=__('Settings', VK_TEXT_DOMAIN);?>
	</h2><form action="" method="post">
	    <input type="hidden" name="saveopts" value="1">
	<h3>1. Vkontakte API ID</h3>
	<table class="form-table">
	<tbody>
	<tr valign="top">
	    <th scope="row">VKontakte API ID</th>
	    <td>
		<input type="text" value="<?=get_option('vkwallpost_api_id');?>" name="vkwallpost_api_id" id="vkwallpost_api_id" class="regular-text" style="width:100px;" id="vkwallpost_api_id">
	    </td>
	</tr>
	<tr>
		<td colspan="2">
		    <?=__("To get the APP ID you should follow some easy steps, described below.<br/><br/><ul><li>1. Open <a href=\"http://vkontakte.ru/editapp?act=create&site=1\" target=\"_blank\">this link</a> to connect your site to Vkontakte social.</li><li>2. On form, named <b>\"Connection to API\"</b> type the name of your web site in <b>\"Title\"</b> field.<br/>In this field you can write almost any name e.g. the adress of your<br/>web site. This name will be displayed in the future as signature under your wall post.</li><li>3. The radiobutton <b>\"Category\"</b> should be switched on <b>\"Website\"</b> (default choice).</li><li>4. In the <b>\"Site address\"</b> field please type full address of your web site e.g.: <code>http://www.yoursite.com/</code></li><li>5. In <b>\"Home domain\"</b> field you should type the home domain of your site without \"www\" e.g.: <code>http://yoursite.com/</code></li><li>6. Now you are ready to connect your site. To do so - just click on <b>\"Connect site\"</b> button and in poupup window<br/>type the text from picture.If everything is fine you will be on site settings page.</li><li>7. On site settings page you should copy the value from <b>\"Application ID\"</b> field<br/>and paste it into <b>\"VKontakte API ID\"</b> field above on this page.<br/>If you wish, you can add an icon and description for your site on settings page as well.</li></ul>",VK_TEXT_DOMAIN);?>
		</td>
	</tr>
	</tbody>
	</table>
	<h3>2. <?=__("Publication", VK_TEXT_DOMAIN);?></h3>
	<table class="form-table">
	<tbody>
	<tr valign="top">
	    <th scope="row"><?=__("The target of Post",VK_TEXT_DOMAIN);?>:</th>
	    <td>
                <label><input type="radio" id="isOwnWall" name="vkwallpost_is_group" value="0"<? if(!get_option('vkwallpost_is_group')) print(' checked="yes"');?>>&nbsp;<b><?=__("Own wall",VK_TEXT_DOMAIN);?></b></label>
	    </td>
	</tr>
    </tbody>
    </table>

    <div id="group-block">
	<table class="form-table" style="margin-top:0px;">
	<tbody>
	<tr valign="top">
	    <th scope="row"></th>
	    <td>
                <label><input type="radio" id="isGroupWall" name="vkwallpost_is_group" value="1"<? if(get_option('vkwallpost_is_group'))  print(' checked="yes"');?>>&nbsp;<?=__("<b>Group wall</b> (only if you are administartor of that group)",VK_TEXT_DOMAIN);?></label>
	    </td>
	</tr>
    </tbody>
    </table>
    <!-- only for group -->
    <table class="form-table" id="table_group" style="display: none;">
    <tbody>
	<tr valign="top" id="tr_group_id">
	    <th scope="row"><?=__("Group id:",VK_TEXT_DOMAIN);?></th>
	    <td>
                <input type="text" value="<?=get_option('vkwallpost_group_id');?>" name="vkwallpost_group_id" class="regular-text" id="vkwallpost_group_id">
	    </td>
	</tr>
	<tr valign="top" id="tr_from_group">
	    <th scope="row"><?=__("Publish from group name",VK_TEXT_DOMAIN);?>:</th>
	    <td>
		<input type="checkbox" name="vkwallpost_publish_fromgroup" id="vkwallpost_publish_fromgroup" value="1"<?=get_option('vkwallpost_publish_fromgroup') ? " checked" : "";?>><?=__("yes",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
    <tbody>
    </table>
    <!-- eo only for group -->
    </div>
    <table class="form-table">
    <tbody>
	<tr valign="top">
	    <th scope="row"><?=__("Attach link to the post:<br/><span style=\"font-size:11px;\">(the link to your post will be attached with the first picture taken from your post)</span>",VK_TEXT_DOMAIN);?></th>
	    <td>
		<input type="checkbox" name="vkwallpost_attach_link" id="vkwallpost_attach_link" value="1"<?=get_option('vkwallpost_attach_link') ? " checked" : "";?>><?=__("yes",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
	<tr valign="top">
	    <th scope="row"><?=__("Post visble to friends only",VK_TEXT_DOMAIN);?>:</th>
	    <td>
		<input type="checkbox" name="vkwallpost_friends_only" id="vkwallpost_friends_only" value="1"<?=get_option('vkwallpost_friends_only') ? " checked" : "";?>><?=__("yes",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
	<tr valign="top">
	    <th scope="row"><?=__("Post template:<br/><span style=\"font-size:11px;\">(defines the type of data to send on your wall)</span>",VK_TEXT_DOMAIN);?></th>
	    <td>
		<textarea name="vkwallpost_template" id="vkwallpost_template"><?=get_option('vkwallpost_template');?></textarea><br/>
		    <?=__("<u>You can use the following:</u><ul><li><code>#permalink#</code> - the user readable link on your post depending on your permanent link format <a href=\"options-permalink.php\">settings</a>.</li><code>#url#</code> - the link on your post in wordpress format (like ?p=id) this link is always unchangeable rather than permalinks that you can change.</li><li><code>#title#</code> - title</li><li><code>#post#</code> - post content without tags</li><li><code>#date#</code> - publication date</li><li><code>#time#</code> - publication time</li></ul>",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
	<tr valign="top">
	    <th scope="row"><?=__("Symbols count for post content:<br><span style=\"font-size:11px;\">(only if you use <code>#post#</code> tag, if you do not want to cut the text - just set the value to 0)</span>",VK_TEXT_DOMAIN);?></th>
	    <td>
		<input type="text" value="<?=get_option('vkwallpost_symbols_count');?>" name="vkwallpost_symbols_count" class="regular-text" id="vkwallpost_symbols_count" style="width:50px;">
		<label for="vkwallpost_symbols_count" class="description"></label>
	    </td>
	</tr>
	<tr valign="top">
	    <th scope="row"><?=__("Checkbox \"Publish to Vkontakte wall\" checked by default",VK_TEXT_DOMAIN);?>:</th>
	    <td>
		<input type="checkbox" name="vkwallpost_default_ischecked" id="vkwallpost_default_ischecked" value="1"<?=get_option('vkwallpost_default_ischecked') ? " checked" : "";?>><?=__("yes",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
	<tr valign="top">
	    <th scope="row"><?=__("Allow to publish to Vkontakte wall when creating page",VK_TEXT_DOMAIN);?>:</th>
	    <td>
		<input type="checkbox" name="vkwallpost_post_pages" id="vkwallpost_post_pages" value="1"<?=get_option('vkwallpost_post_pages') ? " checked" : "";?>><?=__("yes",VK_TEXT_DOMAIN);?>
	    </td>
	</tr>
	</tbody>
	</table>
    <script language="javascript"><?php
        echo (get_option('vkwallpost_is_group')) ? 'setGroupBlockActive();' : 'setGroupBlockInActive();';
    ?></script>

	<p class="submit">
	<input type="submit" value="<?=__("Save settings",VK_TEXT_DOMAIN);?>" class="button-primary">
	</p>
	</form>
	</div>
<?php  }
}
?>